<?php
require '/home/users/satoshiskleinazeigen/www/staging.satoshiskleinanzeigen.space/wp-content/plugins/sk-core/lib/autoload.php';
use WebSocket\Client;
$market = '88a51fe4e011c908789594e1e00e2171cacc25a09b17e3ee25d7078283c1c21f';

$relays = [ 'wss://nos.lol', 'wss://relay.primal.net', 'wss://relay.damus.io', 'wss://relay.snort.social',
            'wss://relay.guggero.org', 'wss://nostr.hifish.org' ];

foreach ( $relays as $r ) {
    printf( "%-26s ", str_replace('wss://','',$r) );
    try {
        $c = new Client($r); $c->setTimeout(7);
        $c->text(json_encode(['REQ','in',['kinds'=>[1059,4],'#p'=>[$market],'since'=>time()-5400,'limit'=>20]]));
        $s=time(); $rows=[];
        while (time()-$s<7) {
            try { $m=$c->receive(); } catch(\Throwable $e){ break; }
            $d=json_decode(method_exists($m,'getContent')?$m->getContent():(string)$m,true);
            if(!is_array($d))continue;
            if(($d[0]??'')==='EVENT') $rows[] = 'kind '.$d[2]['kind'].' @'.date('H:i:s',$d[2]['created_at']).' '.substr($d[2]['id'],0,10);
            if(($d[0]??'')==='EOSE')break;
        }
        $c->close();
        echo $rows ? count($rows)." → ".implode(' | ',array_slice($rows,0,4))."\n" : "nothing\n";
    } catch(\Throwable $e){ echo "unreachable\n"; }
}
