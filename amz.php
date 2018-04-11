<?php
define('MAGPIE_CACHE_ON', 1); //キャッシュ有効
define('MAGPIE_CACHE_DIR', './cache');  //キャッシュディレクトリ
define('MAGPIE_CACHE_AGE', 60*60); //キャッシュ有効時間（秒）
define('MAGPIE_CACHE_FRESH_ONLY', 0);
define('MAGPIE_DEBUG', 0);  //デバッグ情報出力
define('MAGPIE_USER_AGENT', 'MagpieRSS/0.3 (+http://magpierss.sf.net)' ); //RSSファイルを取得する際のUserAgent名
define('MAGPIE_FETCH_TIME_OUT', 5);	//RSSファイルを取得するときのタイムアウト時間
define('MAGPIE_USE_GZIP', 0);

require_once('magpie/rss_fetch.inc');
//require_once('config.php');
function bookSearch($key){
  define('AMZ_ACCESS_KEY', getenv("AMZ_ACCESS_KEY"));
  define('AMZ_ACCESS_SECRET', getenv("AMZ_ACCESS_SECRET"));
  $access_key_id = AMZ_ACCESS_KEY;
  $secret_access_key = AMZ_ACCESS_SECRET;

  // RFC3986 形式で URL エンコードする関数
  function urlencode_rfc3986($str){
    return str_replace('%7E', '~', rawurlencode($str));
  }
  // 基本的なリクエストを作成します
  // - この部分は今まで通り
  $baseurl = 'http://ecs.amazonaws.jp/onca/xml';
  $params = array();
  $params['Service']        = 'AWSECommerceService';
  $params['AWSAccessKeyId'] = $access_key_id;
  $params['Version']        = '2009-03-31';
  $params['Operation']      = 'ItemSearch'; // ← ItemSearch オペレーションの例
  #$params['SearchIndex']    = 'Books';
  $params['SearchIndex']    = 'All';
  $params['Keywords']       = $key;     // ← 文字コードは UTF-8
  $params["AssociateTag"]   = "inajob-22";
  $params["ResponseGroup"]   = "Large,Images,Similarities,Offers";
  $params['Timestamp'] = gmdate('Y-m-d\TH:i:s\Z');

  // パラメータの順序を昇順に並び替えます
  ksort($params);

  // canonical string を作成します
  $canonical_string = '';
  foreach ($params as $k => $v) {
    $canonical_string .= '&'.urlencode_rfc3986($k).'='.urlencode_rfc3986($v);
  }
  $canonical_string = substr($canonical_string, 1);

  // 署名を作成します
  // - 規定の文字列フォーマットを作成
  // - HMAC-SHA256 を計算
  // - BASE64 エンコード
  $parsed_url = parse_url($baseurl);
  $string_to_sign = "GET\n{$parsed_url['host']}\n{$parsed_url['path']}\n{$canonical_string}";
  $signature = base64_encode(hash_hmac('sha256', $string_to_sign, $secret_access_key, true));

  // URL を作成します
  // - リクエストの末尾に署名を追加
  $url = $baseurl.'?'.$canonical_string.'&Signature='.urlencode_rfc3986($signature);

#echo $url;

  $str =  _fetch_remote_file($url)->results;
  $xml = simplexml_load_string($str);
  $ret = array();
  foreach($xml->Items->Item as $i){
    $item = array();

    $item["asin"] = $i->ASIN;
    $item["link"] = $i->DetailPageURL;
    if(isset($i->SmallImage->URL)){
      $item["image"] = $i->SmallImage->URL;
    }else{
      $item["image"] = NULL;
    }
    if(isset($i->MediumImage->URL)){
      $item["mimage"] = $i->MediumImage->URL;
    }else{
      $item["mimage"] = NULL;
    }
    if(isset($i->LeargeImage->URL)){
      $item["limage"] = $i->LeargeImage->URL;
    }else{
      $item["limage"] = NULL;
    }

    if(isset($i->ItemAttributes)){
      foreach($i->ItemAttributes as $ia){
        if(isset($ia->NumberOfPages)){
          $item['NumberOfPages'] = $ia->NumberOfPages;
        }
        if(isset($ia->Author)){
          $item['Author'] = $ia->Author;
        }
      }
    }
    $item['similarProducts'] = array();
    if(isset($i->SimilarProducts)){
      foreach($i->SimilarProducts->SimilarProduct as $sp){
        if(isset($sp->ASIN)){
          $item['similarProducts'][] = array(
            'asin' => $sp->ASIN,
            'title' => $sp->Title,
          );
        }
      }
    }
    $item["title"] = $i->ItemAttributes->Title;
    if(isset($i->Offers) && isset($i->Offers->Offer)){
      $item["price"] = $i->Offers->Offer->OfferListing->Price->FormattedPrice;
    }else{
      $item["price"] = $i->ItemAttributes->ListPrice->FormattedPrice;
    }
    array_push($ret,$item);
  }

  return $ret;
  }

$q = $_GET['q'];
$cv = $_GET['callback'];
if(empty($q)){
  $q='test';
 }
if(empty($cv)){
  echo json_encode(bookSearch($q));
}else{
  header("Content-Type: text/javascript; charset=utf-8");
  echo $cv . '(' . json_encode(bookSearch($q)) . ')';
}
?>
