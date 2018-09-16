<?php
if(!isset($argv[1]))
    die("Usage:\n\tphp $argv[0] [v] - list lang\n\tphp $argv[0] [v] [lang] - download caption\n\n[v] is v query of youtube");

$scc=stream_context_create(['ssl'=>[
    "verify_peer"=>false,
    "verify_peer_name"=>false,
]]);

function srt_sync($t, $d){
    return srt_time((int)$t).' --> '.srt_time((int)$t+(int)$d);
}
function srt_time($time){
    $ms=$time%1000;
    $h=$time/(60*60*1000);
    $m=($time/(60*1000))%60;
    $s=($time/(1000))%60;
    
    return sprintf('%02d:%02d:%02d,%03d', $h, $m, $s, $ms);
}

$ret=file_get_contents('https://www.youtube.com/watch?v='.$argv[1], false, $scc);

preg_match('/ytplayer\.config\s*=\s*(\{.*\})\;\s*ytplayer/', $ret, $m);
$config=json_decode($m[1]);
$player_response=json_decode($config->args->player_response);
if(!isset($player_response->captions)){
    echo "no caption!\n";
    exit();
}
$captions=$player_response->captions->playerCaptionsTracklistRenderer->captionTracks;

if(isset($argv[2])){
    foreach($captions as $c){
        if($argv[2]===$c->languageCode){
            $title=preg_replace('/\\\\|\/|\:|\*|\?|\"|\<|\>|\|/', '_', $config->args->title); // Can't contain charator in filename
            $srt_file=$title.' - YouTube.srt';

            $xml=simplexml_load_string(file_get_contents($c->baseUrl.'&fmt=srv3', false, $scc));
            
            $srt=fopen($srt_file, 'wt');
            $seq=0;
            $p=$xml->body->p;
            for($i=0, $len=count($p); $i<$len; $i++){
                fwrite($srt, ++$seq."\n");
                
                if(isset($p[$i]->s)){
                    $t=$p[$i]['d'];
                    if($i+1<$len)
                        $t=$p[$i+1]['t']-$p[$i]['t'];

                    fwrite($srt, srt_sync($p[$i]['t'], $t)."\n");
                    for($g=0; $g<count($p[$i]->s); $g++)
                        fwrite($srt, $p[$i]->s[$g]);
                    fwrite($srt, "\n");
                }else{
                    fwrite($srt, srt_sync($p[$i]['t'], $p[$i]['d'])."\n");
                    fwrite($srt, trim($p[$i])."\n");
                }
            }
            fclose($srt);

            echo $srt_file."\n";
            exit();
        }
    }
    echo "no this caption!\n";
}else{
    echo 'title: '.$config->args->title."\n";
    echo "captions: \n";
    foreach($captions as $c)
        echo "\t".$c->name->simpleText.' ['.$c->languageCode.']'."\n";
}