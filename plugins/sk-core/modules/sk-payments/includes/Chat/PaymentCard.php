<?php

namespace SK\Modules\Payments\Chat;

use SK\Modules\Payments\QrImage;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the payment cards that get rendered inside vendor chats.
 *
 * A chat message is plain user input, so the [lightning_*]/[onchain_*] markers
 * inside it are never trusted. Every card is rebuilt from the
 * sk_lightning_payments row (purchase requests: from the product), and only if
 * that row belongs to this chat. A forged marker can therefore never show an
 * invoice, a QR code or a confirmation that the database does not back.
 */
class PaymentCard {

	/** Marker name => card type handed to the frontend. */
	const TYPES = [
		'lightning_purchase_request'  => 'purchase_request',
		'lightning_invoice'           => 'lightning_invoice',
		'lightning_payment_confirmed' => 'payment_confirmed',
		'onchain_payment'             => 'onchain_payment',
		'onchain_confirmed'           => 'onchain_confirmed',
	];

	/** Payment states that count as "money has arrived". */
	const SETTLED_STATES = [ 'confirmed', 'delivered', 'disputed' ];

	/**
	 * Does this text claim to carry a payment card?
	 */
	public static function has_marker( string $text ): bool {
		return (bool) preg_match( self::marker_pattern(), $text );
	}

	/**
	 * Remove payment marker blocks (including their payload) from a text.
	 */
	public static function strip_markers( string $text ): string {
		$names = implode( '|', array_keys( self::TYPES ) );

		// Whole blocks first, then any leftover opening/closing tag.
		$text = preg_replace( '#\[(?:' . $names . ')\].*?\[/(?:' . $names . ')\]#is', '', $text );
		$text = preg_replace( '#\[/?(?:' . $names . ')\]#i', '', (string) $text );

		return trim( preg_replace( '/[ \t]+/', ' ', (string) $text ) );
	}

	/**
	 * What to show as message text: markers never reach the reader verbatim.
	 */
	public static function to_display_text( string $text ): string {
		if ( ! self::has_marker( $text ) ) {
			return $text;
		}

		$stripped = self::strip_markers( $text );

		return $stripped !== '' ? $stripped : __( '⚡ Zahlungsnachricht', 'sk-core' );
	}

	/**
	 * Rebuild a verified card for one chat message.
	 *
	 * @param array $message Message entry as returned by ChatMessages::all().
	 * @param int   $chat_id Chat the message belongs to.
	 * @return array|null Card data, or null if nothing verifiable.
	 */
	public static function build( array $message, int $chat_id ): ?array {
		if ( ! $chat_id ) {
			return null;
		}

		// Messages written since the move to the sk_chat_messages table carry the
		// reference in their own columns, so nothing has to be parsed out of text.
		$claim = self::claim_from_columns( $message, $chat_id );

		// Older rows only have the marker in the message body.
		if ( ! $claim ) {
			$claim = self::extract_claim( (string) ( $message['message'] ?? '' ) );
		}

		if ( ! $claim ) {
			return null;
		}

		if ( $claim['marker'] === 'lightning_purchase_request' ) {
			return self::build_purchase_request( $claim['data'], $chat_id );
		}

		return self::build_from_payment(
			$claim['marker'],
			$claim['data'],
			$chat_id,
			(int) ( $message['user_id'] ?? 0 )
		);
	}

	/**
	 * Build the claim from the message's own columns.
	 *
	 * Same shape as extract_claim() so both paths verify identically.
	 */
	private static function claim_from_columns( array $message, int $chat_id ): ?array {
		$card_type = (string) ( $message['card_type'] ?? '' );

		if ( $card_type === '' ) {
			return null;
		}

		$marker = array_search( $card_type, self::TYPES, true );

		if ( $marker === false ) {
			return null;
		}

		$data = [];

		if ( ! empty( $message['payment_hash'] ) ) {
			$data['payment_hash'] = (string) $message['payment_hash'];
		}

		// Purchase requests reference a product instead of a payment.
		// build_purchase_request() compares the claim against the chat's product,
		// exactly as it does for the marker payload.
		if ( $marker === 'lightning_purchase_request' ) {
			$data['product_id'] = (int) get_post_meta( $chat_id, '_dvc_product_id', true );
		}

		return [
			'marker' => $marker,
			'data'   => $data,
		];
	}

	/**
	 * Find the first payment marker and decode its payload.
	 *
	 * The payload is only used to look the payment up — never for display.
	 */
	private static function extract_claim( string $text ): ?array {
		foreach ( array_keys( self::TYPES ) as $marker ) {
			$pattern = '#\[' . $marker . '\](.*?)\[/' . $marker . '\]#is';
			if ( ! preg_match( $pattern, $text, $m ) ) {
				continue;
			}

			$data = json_decode( trim( $m[1] ), true );

			return [
				'marker' => $marker,
				'data'   => is_array( $data ) ? $data : [],
			];
		}

		return null;
	}

	/**
	 * Purchase request: no payment row exists yet, so the product is the source
	 * of truth. Title and price always come from the product, never from the
	 * message — otherwise a buyer could request "this product for 1 sat".
	 */
	private static function build_purchase_request( array $data, int $chat_id ): ?array {
		$chat_product = (int) get_post_meta( $chat_id, '_dvc_product_id', true );
		$claimed      = isset( $data['product_id'] ) ? (int) $data['product_id'] : 0;

		if ( ! $chat_product || $claimed !== $chat_product ) {
			return null;
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $chat_product );
		if ( ! $product ) {
			return null;
		}

		// Der Schluessel der Ausfuehrung steht in der Nachricht, der Betrag
		// dazu kommt aus dem Inserat — genau wie der Grundpreis.
		$variant_key = isset( $data['variant'] ) ? (string) $data['variant'] : '';

		$price_sats = \SK\Modules\Payments\Variant::price( $product, $variant_key );
		if ( $price_sats < 1 ) {
			return null;
		}

		return [
			'type'          => 'purchase_request',
			'product_id'    => $chat_product,
			'product_title' => \SK\Modules\Payments\Variant::title( $product, $variant_key ),
			'price_sats'    => $price_sats,
		];
	}

	/**
	 * Every other card type is backed by a sk_lightning_payments row.
	 */
	private static function build_from_payment( string $marker, array $data, int $chat_id, int $sender_id ): ?array {
		$hash = isset( $data['payment_hash'] ) ? strtolower( (string) $data['payment_hash'] ) : '';
		if ( ! preg_match( '/^[0-9a-f]{64}$/', $hash ) ) {
			return null;
		}

		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT vendor_id, buyer_id, product_id, amount_sats, payment_request, status, context, chat_id, verify_url, preimage
			 FROM {$wpdb->prefix}sk_lightning_payments
			 WHERE payment_hash = %s",
			$hash
		) );

		// The payment must exist and belong to exactly this chat.
		if ( ! $row || (int) $row->chat_id !== $chat_id ) {
			return null;
		}

		$vendor_id = (int) $row->vendor_id;
		$buyer_id  = (int) $row->buyer_id;

		// Only the two parties of the payment can have posted the card.
		if ( $sender_id !== $vendor_id && $sender_id !== $buyer_id ) {
			return null;
		}

		$amount  = (int) $row->amount_sats;
		$status  = (string) $row->status;
		$settled = in_array( $status, self::SETTLED_STATES, true );

		switch ( $marker ) {
			case 'lightning_invoice':
				if ( $row->context !== 'chat' || $sender_id !== $vendor_id ) {
					return null;
				}

				$bolt11 = (string) $row->payment_request;
				if ( ! preg_match( '/^ln[a-z0-9]{20,}$/i', $bolt11 ) ) {
					return null;
				}

				return [
					'type'            => 'lightning_invoice',
					'payment_hash'    => $hash,
					'amount_sats'     => $amount,
					'payment_request' => $bolt11,
					'deeplink'        => 'lightning:' . $bolt11,
					'qr'              => QrImage::bolt11( $bolt11 ),
					'status'          => $status,
					'settled'         => $settled,
				];

			case 'onchain_payment':
				if ( $row->context !== 'onchain' || $sender_id !== $buyer_id ) {
					return null;
				}

				$address = (string) $row->verify_url;
				if ( ! preg_match( '/^[a-zA-Z0-9]{20,90}$/', $address ) ) {
					return null;
				}

				$btc_amount = number_format( $amount / 100000000, 8, '.', '' );
				$bip21      = 'bitcoin:' . $address . '?amount=' . $btc_amount;

				return [
					'type'          => 'onchain_payment',
					'payment_hash'  => $hash,
					'amount_sats'   => $amount,
					'address'       => $address,
					'btc_amount'    => $btc_amount,
					'bip21'         => $bip21,
					'qr'            => QrImage::data_uri( $bip21 ),
					'product_title' => $row->product_id ? get_the_title( (int) $row->product_id ) : '',
					'status'        => $status,
					'settled'       => $settled,
				];

			case 'lightning_payment_confirmed':
			case 'onchain_confirmed':
				// No confirmation card unless the payment really is settled.
				if ( ! $settled ) {
					return null;
				}

				$card = [
					'type'         => self::TYPES[ $marker ],
					'payment_hash' => $hash,
					'amount_sats'  => $amount,
					'status'       => $status,
				];

				// Onchain stores the txid in the preimage column.
				$txid = strtolower( (string) $row->preimage );
				if ( $row->context === 'onchain' && preg_match( '/^[0-9a-f]{64}$/', $txid ) ) {
					$card['txid'] = $txid;
				}

				return $card;
		}

		return null;
	}

	/**
	 * Note: [nostr_order] is deliberately not a card marker — it carries no
	 * renderer and its body is order text the vendor needs to read.
	 */
	private static function marker_pattern(): string {
		return '#\[/?(?:' . implode( '|', array_keys( self::TYPES ) ) . ')\]#i';
	}
}
