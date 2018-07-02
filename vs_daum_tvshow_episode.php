<?
/***
* 상세한 영상 정보와 회차(episode)정보를 가공하여 TVDB와 같은 형식으로 Video Station에 제공한다.
***/

$fanart = true; // tvdb의 fanart 정보를 가져오려면 true로 설정. 검색 속도가 느려질 수 있다.
$keyword=$_GET['id'];
$seriesurl="http://movie.daum.net/tv/main?tvProgramId=".$keyword;
$episodeurl="http://movie.daum.net/tv/episode?tvProgramId=".$keyword;
$seriesData=array();
$episodeData=array();
$options = array(
	'http'=>array(
	'method'=>"GET",
	'header'=>"Accept-language: en\r\n" .
			"Cookie: foo=bar\r\n" .  
			"User-Agent: Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.102011-10-16 20:23:10\r\n" 
	)
);
getSeries($seriesurl);
getEpisodes($episodeurl);
makeXML();

function makeXML(){
	global $seriesData,$episodeData;
	$xml = "<?xml version='1.0' encoding='UTF-8'?>";
	$xml .= "<Data>";
	$xml .= "<Series>";
	$xml .= "<id>".$seriesData["id"]."</id>";
	$xml .= "<FirstAired>".$seriesData["FirstAired"]."</FirstAired>";
	$xml .= "<Genre>".$seriesData["Genre"]."</Genre>";
	$xml .= "<Language>".$seriesData["Language"]."</Language>";
	$xml .= "<Network>".$seriesData["Network"]."</Network>";
	$xml .= "<Overview>".$seriesData["Overview"]."</Overview>";
	$xml .= "<SeriesName>".$seriesData["SeriesName"]."</SeriesName>";
	$xml .= "<poster>".$seriesData["poster"]."</poster>";
	$xml .= "<fanart>".$seriesData["fanart"]."</fanart>";
	$xml .= "</Series>";
	for($i=0;$i<count($episodeData);$i++){
	$xml .= "<Episode>";
	$xml .= "<id>".$episodeData[$i]['id']."</id>";
	$xml .= "<EpisodeName>".$episodeData[$i]['title']."</EpisodeName>";
	$xml .= "<EpisodeNumber>".$episodeData[$i]['EpisodeNumber']."</EpisodeNumber>";
	$xml .= "<FirstAired>".$episodeData[$i]['FirstAired']."</FirstAired>";
	$xml .= "<Overview>".$episodeData[$i]['Overview']."</Overview>";
	$xml .= "<SeasonNumber>".$episodeData[$i]['SeasonNumber']."</SeasonNumber>";
	$xml .= "<seasonid>".$episodeData[$i]['seasonid']."</seasonid>";
	$xml .= "<seriesid>".$episodeData[$i]['seriesid']."</seriesid>";
	$xml .= "</Episode>";
	}
	$xml .= "</Data>";
	header('Content-type: text/xml');
	echo $xml;
}

function getSeries($url){
	global $seriesData,$options,$fanart;
	$context=stream_context_create($options);
	$rawdata=file_get_contents($url,false,$context);
	preg_match('/tvProgramId=(\d+)/i',$rawdata,$id);
	preg_match('/<dt>현재<\/dt>.+?(\d+?\.\d+?\.\d+?)~.+?<\/dd>/is',$rawdata,$startDate);
	preg_match('/<dt>장르<\/dt><dd class="f_l">(.+?)<\/dd>/i',$rawdata,$genre);
	preg_match('/<dt>국가<\/dt>.+?<\/span>(.+?)<\/dd>/is',$rawdata,$language);
	preg_match('/<dt>방송국 및 방영시간<\/dt>.+?g">(.+?)<\/em>/is',$rawdata,$Network);
	preg_match('/<meta property="og:description" content="(.+?)">/i',$rawdata,$Overview);
	preg_match('/<title>(.+?)-.+?<\/title>/i',$rawdata,$SeriesName);
	preg_match('/<div class="detail_summarize">.+?<img src="(.+?)" class/is',$rawdata,$poster);
	$seriesData['id']=$id[1];
	$tempdate=explode(".",$startDate[1]);
	$seriesData['FirstAired']=$tempdate[0]."-".$tempdate[1]."-".$tempdate[2];
	$seriesData['Genre']=$genre[1];
	switch(trim($language[1])){
			case "대한민국":
				$seriesData['Language']="ko";
				break;
			case "미국":
				$seriesData['Language']="en";
				break;
			case "중국":
				$seriesData['Language']="cn";
				break;
			case "일본":
				$seriesData['Language']="jp";
				break;
			default:
				$seriesData['Language']="ko";
	}
	$seriesData['Network']=$Network[1];
	$seriesData['Overview']=$Overview[1];
	$seriesData['SeriesName']=trim($SeriesName[1]);
	$seriesData['poster']=$poster[1];
	if($fanart) $seriesData['fanart']=getFanArtfromTVDB($seriesData['SeriesName']);
}

function getEpisodes($url){
	global $episodeData,$options;
	$context=stream_context_create($options);
	$rawdata=file_get_contents($url,false,$context);
	preg_match('/MoreView.init\(\d+?,\s(.+?])\);/is',$rawdata,$epidata);
	$jsonData=json_decode($epidata[1],true);
	for($i=0;$i<count($jsonData);$i++){
		$year=substr($jsonData[$i]['channels'][0]["broadcastDate"],0,4);
		$month=substr($jsonData[$i]['channels'][0]["broadcastDate"],4,2);
		$day=substr($jsonData[$i]['channels'][0]["broadcastDate"],6,2);
		$episodeData[$i]['id']=$jsonData[$i]['episodeId'];
		if($jsonData[$i]['title']="") $jsonData[$i]['title']=$jsonData[$i]['name']."회";
		$episodeData[$i]['EpisodeName']=$jsonData[$i]['title'];
		$episodeData[$i]['EpisodeNumber']=$jsonData[$i]['name'];
		$episodeData[$i]['FirstAired']=$year."-".$month."-".$day;
		$episodeData[$i]['Overview']=preg_replace("/[#\&\+\-%@=\/\\\:;,'\"\^`~\_|\!\?\*$#<>()\[\]\{\}]/i", "",strip_tags($jsonData[$i]['introduceDescription']));
		$episodeData[$i]['SeasonNumber']=1;
		$episodeData[$i]['seasonid']=1;
		$episodeData[$i]['seriesid']=$jsonData[$i]['programId'];
	}

}

function getFanArtfromTVDB($title){
	global $options;
	$context=stream_context_create($options);
	$url1 = "https://www.thetvdb.com/api/GetSeries.php?language=ko&seriesname=".urlencode($title);
	$url2 = "http://thetvdb.com/api/1D62F2F90030C444/series/";
	preg_match('/<seriesid>(\d+?)<\/seriesid>/i',HTTPGETRequest($url1),$seriesid);
	preg_match('/<fanart>(.+?)<\/fanart>/i',HTTPGETRequest($url2.$seriesid[1]),$fanartid);
	$fanart=(string)$fanartid[1];
	return $fanart;
}

function HTTPGETRequest($url)
{
	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_URL, $url); 
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
	curl_setopt($ch, CURLOPT_HEADER, 0); 
	curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_5_1; de-de) AppleWebKit/527+ (KHTML, like Gecko) Version/3.1.1 Safari/525.20'); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_POST, 1); 
	$data = curl_exec($ch); 
	curl_close($ch);
	return $data;
}


?>