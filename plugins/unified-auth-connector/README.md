# Unified Auth Connector

A WordPress plugin that enables unified authentication by linking LNURL-Auth (Lightning Network) and Nostr Login to a single WordPress account.

## Description

Unified Auth Connector allows users to link multiple authentication methods to their WordPress account. Users can log in using either Nostr keys or LNURL-Auth (Lightning wallet), and both methods will authenticate to the same WordPress account while preserving the original account's profile data.

## Features

- **Multi-Method Authentication**: Link both Nostr and LNURL-Auth to a single WordPress account
- **Seamless Login**: Users can log in with any linked authentication method
- **Profile Preservation**: The primary account's profile data and settings are maintained
- **Dokan Integration**: Adds an "Authentication" page to the Dokan vendor dashboard
- **Easy Linking/Unlinking**: Simple interface for managing authentication methods
- **Security**: Uses NIP-98 for Nostr authentication and standard LNURL-Auth flow
- **Admin Dashboard**: Statistics and configuration options in WordPress admin

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- **Required Plugins**:
  - At least one of:
    - [LNURL-Auth](https://github.com/joelmelon/lnurl-auth) by joelmelon
    - [Nostr Login](https://github.com/YEGHRO/nostr-login) by YEGHRO
- **Recommended Plugins**:
  - [Dokan](https://wedevs.com/dokan/) for vendor dashboard integration

## Installation

1. Upload the `unified-auth-connector` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure LNURL-Auth and/or Nostr Login plugins are installed and activated
4. Navigate to Settings → Unified Auth to configure the plugin

## How It Works

### User Flow

1. **Initial Login**: User logs in with their preferred method (WordPress, Nostr, or LNURL-Auth)
2. **Access Dashboard**: Navigate to Dokan Dashboard → Authentication
3. **Link Additional Methods**: Click "Link Nostr Account" or "Link Lightning Wallet"
4. **Authenticate**:
   - For Nostr: Sign with browser extension (nos2x, Alby, etc.)
   - For LNURL: Scan QR code with Lightning wallet
5. **Unified Access**: User can now log in using any linked method

### Technical Flow

1. **Primary Account**: The first account created retains all profile data
2. **Identity Mapping**: Authentication identities (Nostr pubkey, LNURL node key) are mapped to the primary WordPress user ID
3. **Login Interception**: When a linked identity attempts to log in, the plugin intercepts and authenticates to the primary account
4. **Data Storage**:
   - Nostr public keys: `uac_linked_nostr_pubkey` user meta
   - LNURL node keys: `uac_linked_lnurl_node_key` user meta
   - Primary auth method: `uac_primary_auth_method` user meta

## Usage

### For Users

#### Linking Nostr Account

1. Log in to your WordPress account
2. Go to Dokan Dashboard → Authentication
3. Click "Link Nostr Account"
4. Approve the authentication request in your Nostr browser extension
5. Your Nostr identity is now linked!

#### Linking Lightning Wallet

1. Log in to your WordPress account
2. Go to Dokan Dashboard → Authentication
3. Click "Link Lightning Wallet"
4. Scan the QR code with your Lightning wallet
5. Your Lightning wallet is now linked!

#### Unlinking Authentication Methods

1. Go to Dokan Dashboard → Authentication
2. Find the linked method you want to remove
3. Click "Unlink"
4. Confirm the action

### For Administrators

#### Settings

Navigate to **Settings → Unified Auth** in WordPress admin to:

- View plugin status and dependencies
- Enable/disable account linking
- Control whether users can unlink authentication methods
- View statistics on linked accounts

#### Statistics Available

- Number of users with Nostr linked
- Number of users with LNURL linked
- Number of users with both methods linked

## Integration with Existing Plugins

### LNURL-Auth Integration

The plugin hooks into the LNURL-Auth login flow using the `wp_login` action. When a user authenticates with LNURL, the plugin:

1. Checks if the node linking key is associated with a different primary account
2. If yes, redirects authentication to the primary account
3. If no, allows normal authentication to proceed

### Nostr Login Integration

Similar to LNURL-Auth, the plugin intercepts Nostr logins using the `wp_login` action:

1. Verifies the Nostr public key using NIP-98 authentication
2. Checks for account linkage
3. Redirects to primary account if linked

### Dokan Integration

Adds a new menu item "Authentication" to the Dokan vendor dashboard at position 190, providing a user-friendly interface for managing linked authentication methods.

## Security Considerations

- **NIP-98 Compliance**: Nostr authentication uses the standard NIP-98 HTTP Auth protocol
- **LNURL Standard**: Follows LNURL-Auth specification for Lightning authentication
- **Nonce Protection**: All AJAX requests are protected with WordPress nonces
- **User Verification**: Only the logged-in user can link/unlink their own authentication methods
- **Session Management**: Uses WordPress standard session and cookie management

## Hooks and Filters

### Actions

- `uac_nostr_linked` - Fired when a Nostr account is linked (params: user_id, pubkey)
- `uac_nostr_unlinked` - Fired when a Nostr account is unlinked (params: user_id, pubkey)
- `uac_lnurl_linked` - Fired when an LNURL account is linked (params: user_id, node_key)
- `uac_lnurl_unlinked` - Fired when an LNURL account is unlinked (params: user_id, node_key)

### Filters

- `lnurl_auth_user_id` - Filter LNURL user ID before authentication
- `nostr_login_user_id` - Filter Nostr user ID before authentication

## File Structure

```
unified-auth-connector/
├── unified-auth-connector.php          # Main plugin file
├── README.md                            # This file
├── includes/
│   ├── class-unified-auth-connector.php # Core plugin class
│   ├── class-account-linker.php         # Account linking logic
│   ├── class-lnurl-auth-integration.php # LNURL-Auth integration
│   └── class-nostr-login-integration.php# Nostr Login integration
├── admin/
│   ├── class-admin-settings.php         # Admin settings page
│   └── class-dokan-dashboard.php        # Dokan dashboard integration
└── assets/
    ├── css/
    │   └── unified-auth-connector.css   # Styling
    └── js/
        └── unified-auth-connector.js    # JavaScript functionality
```

## Troubleshooting

### Nostr Extension Not Detected

**Problem**: "Nostr extension not found" error when linking

**Solution**: Install a Nostr browser extension like:
- [nos2x](https://github.com/fiatjaf/nos2x) (Chrome/Firefox)
- [Alby](https://getalby.com/) (Chrome/Firefox)
- [Flamingo](https://flamingo.me/) (Chrome)

### LNURL QR Code Not Scanning

**Problem**: QR code won't scan or times out

**Solution**:
1. Ensure your Lightning wallet supports LNURL-Auth
2. Check that the callback URL is properly configured in LNURL-Auth plugin settings
3. Verify your server is accessible from the internet
4. Try regenerating the QR code

### Account Already Linked Error

**Problem**: "This identity is already linked to another account"

**Solution**: This identity is already associated with a different WordPress account. You need to unlink it from that account first, or use a different authentication method.

### Dokan Menu Not Showing

**Problem**: "Authentication" menu item not visible in Dokan dashboard

**Solution**:
1. Ensure Dokan plugin is installed and activated
2. Verify you're logged in as a vendor/seller
3. Check plugin activation status
4. Clear cache if using a caching plugin

## Support

For issues, questions, or feature requests:

1. Check this README for solutions
2. Review WordPress admin Settings → Unified Auth for plugin status
3. Check browser console for JavaScript errors
4. Enable WordPress debug mode to see PHP errors

## Changelog

### Version 1.0.0
- Initial release
- Nostr Login integration
- LNURL-Auth integration
- Dokan dashboard page
- Admin settings and statistics
- Account linking/unlinking functionality

## Credits

- **LNURL-Auth Integration**: Based on [lnurl-auth](https://github.com/joelmelon/lnurl-auth) by joelmelon
- **Nostr Integration**: Based on [nostr-login](https://github.com/YEGHRO/nostr-login) by YEGHRO
- **Dokan Integration**: For [Dokan Multivendor Marketplace](https://wedevs.com/dokan/)

## License

GPL-2.0+

This plugin is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or any later version.

This plugin is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
