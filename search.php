#!/usr/bin/php
<?php

$TVDB = "fr"; //TVDB의 한국어 정보를 가져오기 위한 옵션 선택 한다. 기본은 프랑스어 선택 시 TVDB의 한국어 정보를 가져온다.
$FANART = true; // tvdb의 fanart 정보를 가져오려면 true로 설정. 검색 속도가 느려질 수 있다.

require_once(dirname(__FILE__) . '/../constant.php');

define('PLUGINID', 'com.synology.TheTVDB');
define('API_URL', 'https://www.thetvdb.com/api/');
define('BANNER_URL', 'https://www.thetvdb.com/banners/');

$DEFAULT_TYPE = 'tvshow_episode';
$DEFAULT_LANG = 'enu';

$SUPPORTED_TYPE = array('tvshow', 'tvshow_episode');
$SUPPORTED_PROPERTIES = array('title');

require_once(dirname(__FILE__) . '/../search.inc.php');

function ConvertToAPILang($lang)
{
	static $map = array(
		'chs' => 'zh', 'cht' => 'zh', 'csy' => 'cs', 'dan' => 'da',
		'enu' => 'en', 'fre' => 'fr', 'ger' => 'de', 'hun' => 'hu',
		'ita' => 'it', 'jpn' => 'ja', 'krn' => 'ko', 'nld' => 'nl',
		'nor' => 'no', 'plk' => 'pl', 'ptb' => 'pt', 'ptg' => 'pt',
		'rus' => 'ru', 'spn' => 'es', 'sve' => 'sv', 'trk' => 'tr',
		'tha' => 'th'
	);

	$ret = isset($map[$lang]) ? $map[$lang] : NULL;
	return $ret;
}

/**
 * @brief download rawdata from website. If we already cache the
 *  	  result, just return cached result
 * @param $url [in] a reuqest url
 * @param $cache_path [in] a expected cache path
 * @return [out] a xml format result
 */
 
function getSearch($keyword){
	$url="https://m.search.daum.net/search?w=tv&q=".$keyword."&irt=tv-program";
	$rawdata=HTTPGETRequest($url);
	preg_match("/smartLog.+?d=(\d+?)\',/i",$rawdata,$dataSet["id"]);
	preg_match("/<span class=\"txt_bar\">.+?(\d{4}\.\d*\.\d*).+?<\/dd>/i",$rawdata,$dataSet["FirstAired"]);
	preg_match("/<dt class=\"tit_base\">.+?<\/dt>\s<dd class=\"cont\">\s<span class=\"txt_info\">(.+?)<\/span>/i",$rawdata,$dataSet["Genre"]);
	preg_match("/방영시간 정보.+?smartLog.+?d=\d+?\', event\);\"> (.+?) <\/a>/i",$rawdata,$dataSet["Network"]);
	return makeXML("search",$dataSet);
}

function getSeries($keyword){
	global $FANART;
	if($keyword==0) return 0;
	$seriesurl="http://movie.daum.net/tv/main?tvProgramId=".$keyword;
	$episodeurl="http://movie.daum.net/tv/episode?tvProgramId=".$keyword;
	$dataSet=array();
	$seriesdata=HTTPGETRequest($seriesurl);
	preg_match('/tvProgramId=(\d+)/i',$seriesdata,$id);
	preg_match('/<dt>현재<\/dt>.+?(\d+?\.\d+?\.\d+?)~.+?<\/dd>/is',$seriesdata,$startDate);
	preg_match('/<dt>장르<\/dt><dd class="f_l">(.+?)<\/dd>/i',$seriesdata,$genre);
	preg_match('/<dt>국가<\/dt>.+?<\/span>(.+?)<\/dd>/is',$seriesdata,$language);
	preg_match('/<dt>방송국 및 방영시간<\/dt>.+?g">(.+?)<\/em>/is',$seriesdata,$Network);
	preg_match('/<meta property="og:description" content="(.+?)">/i',$seriesdata,$Overview);
	preg_match('/<title>(.+?)-.+?<\/title>/i',$seriesdata,$SeriesName);
	preg_match('/<div class="detail_summarize">.+?<img src="(.+?)" class/is',$seriesdata,$poster);
	$dataSet[0]['id']=$id[1];
	$tempdate=explode(".",$startDate[1]);
	$dataSet[0]['FirstAired']=$tempdate[0]."-".$tempdate[1]."-".$tempdate[2];
	$dataSet[0]['Genre']=$genre[1];
	switch(trim($language[1])){
			case "대한민국":
				$dataSet[0]['Language']="ko";
				break;
			case "미국":
				$dataSet[0]['Language']="en";
				break;
			case "중국":
				$dataSet[0]['Language']="cn";
				break;
			case "일본":
				$dataSet[0]['Language']="jp";
				break;
			default:
				$dataSet[0]['Language']="ko";
	}
	$dataSet[0]['Network']=$Network[1];
	$dataSet[0]['Overview']=$Overview[1];
	$dataSet[0]['SeriesName']=trim($SeriesName[1]);
	$dataSet[0]['poster']=$poster[1];
	if($FANART) $dataSet[0]['fanart']=getFanArtfromTVDB($dataSet[0]['SeriesName']);
	$episodedata=HTTPGETRequest($episodeurl);
	preg_match('/MoreView.init\(\d+?,\s(.+?])\);/is',$episodedata,$epidata);
	$jsonData=json_decode($epidata[1],true);
	for($i=0;$i<count($jsonData);$i++){
		$year=substr($jsonData[$i]['channels'][0]["broadcastDate"],0,4);
		$month=substr($jsonData[$i]['channels'][0]["broadcastDate"],4,2);
		$day=substr($jsonData[$i]['channels'][0]["broadcastDate"],6,2);
		$dataSet[1][$i]['id']=$jsonData[$i]['episodeId'];
		if($jsonData[$i]['title']="") $jsonData[$i]['title']=$jsonData[$i]['name']."회";
		$dataSet[1][$i]['EpisodeName']=$jsonData[$i]['title'];
		$dataSet[1][$i]['EpisodeNumber']=$jsonData[$i]['name'];
		$dataSet[1][$i]['FirstAired']=$year."-".$month."-".$day;
		$dataSet[1][$i]['Overview']=preg_replace("/[#\&\+\-%@=\/\\\:;,'\"\^`~\_|\!\?\*$#<>()\[\]\{\}]/i", "",$jsonData[$i]['introduceDescription']);
		$dataSet[1][$i]['SeasonNumber']=1;
		$dataSet[1][$i]['seasonid']=1;
		$dataSet[1][$i]['seriesid']=$jsonData[$i]['programId'];
	}
	return makeXML("series",$dataSet);
}

function makeXML($request,$dataSet){
	if($request=="search"){
		$xml = "<?xml version='1.0' encoding='UTF-8'?>";
		$xml .= "<Data>";
		$xml .= "<Series>";
		$xml .= "<seriesid>".$dataSet["id"][1]."</seriesid>";
		$xml .= "<id>".$dataSet["id"][1]."</id>";
		$xml .= "<FirstAired>".$dataSet["FirstAired"][1]."</FirstAired>";
		$xml .= "<Genre>".$dataSet["Genre"][1]."</Genre>";
		$xml .= "<language>".$dataSet["Language"]."</language>";
		$xml .= "<Network>".$dataSet["Network"][1]."</Network>";
		$xml .= "</Series>";
		$xml .= "</Data>";
	}
	else if ($request == "series"){
		$xml = "<?xml version='1.0' encoding='UTF-8'?>";
		$xml .= "<Data>";
		$xml .= "<Series>";
		$xml .= "<id>".$dataSet[0]["id"]."</id>";
		$xml .= "<FirstAired>".$dataSet[0]["FirstAired"]."</FirstAired>";
		$xml .= "<Genre>".$dataSet[0]["Genre"]."</Genre>";
		$xml .= "<Language>".$dataSet[0]["Language"]."</Language>";
		$xml .= "<Network>".$dataSet[0]["Network"]."</Network>";
		$xml .= "<Overview>".$dataSet[0]["Overview"]."</Overview>";
		$xml .= "<SeriesName>".$dataSet[0]["SeriesName"]."</SeriesName>";
		$xml .= "<poster>".$dataSet[0]["poster"]."</poster>";
		$xml .= "<fanart>".$dataSet[0]["fanart"]."</fanart>";
		$xml .= "</Series>";
		for($i=0;$i<count($dataSet[1]);$i++){
			$xml .= "<Episode>";
			$xml .= "<id>".$dataSet[1][$i]['id']."</id>";
			$xml .= "<EpisodeName>".$dataSet[1][$i]['title']."</EpisodeName>";
			$xml .= "<EpisodeNumber>".$dataSet[1][$i]['EpisodeNumber']."</EpisodeNumber>";
			$xml .= "<FirstAired>".$dataSet[1][$i]['FirstAired']."</FirstAired>";
			$xml .= "<Overview>".$dataSet[1][$i]['Overview']."</Overview>";
			$xml .= "<SeasonNumber>".$dataSet[1][$i]['SeasonNumber']."</SeasonNumber>";
			$xml .= "<seasonid>".$dataSet[1][$i]['seasonid']."</seasonid>";
			$xml .= "<seriesid>".$dataSet[1][$i]['seriesid']."</seriesid>";
			$xml .= "</Episode>";
		}
		$xml .= "</Data>";
	}
	else if ($request == "actors"){
			$xml = "<?xml version='1.0' encoding='UTF-8'?>";
			$xml .= "<Actors> </Actors>";
	}
	return $xml;
}

function getFanArtfromTVDB($title){
	$url1 = "https://www.thetvdb.com/api/GetSeries.php?language=ko&seriesname=".urlencode($title);
	$url2 = "http://thetvdb.com/api/1D62F2F90030C444/series/";
	preg_match('/<seriesid>(\d+?)<\/seriesid>/i',HTTPGETRequest($url1),$seriesid);
	preg_match('/<fanart>(.+?)<\/fanart>/i',HTTPGETRequest($url2.$seriesid[1]),$fanartid);
	return (string)$fanartid[1];
}

/***
* url에 daum이 포함 될 경우 daum 데이터 받아 오도록 분기를 추가 함
***/
function DownloadRawdata($url, $cache_path, $zip)
{
	$xml = FALSE;
	$need_refresh = TRUE;

	//Whether cache file already exist or not
	if (file_exists($cache_path)) {
		$lastupdated = filemtime($cache_path);
		if (86400 >= (time() - $lastupdated)) {
			$xml = GetStripedXML($cache_path);
			if (NULL !== $xml) {
				$need_refresh = FALSE;
			}
		}
	}

	//If we need refresh cache file, grab rawdata from url website
	if ($need_refresh) {
		//create dir
		$path_parts = pathinfo($cache_path);
		$dir = $path_parts['dirname'];
		if(!file_exists($dir)) {
			mkdir($dir, 0755, true);
		}

		//download a zip file
		if ($zip) {
			$zip_path = $dir . '/tmp.zip';
			$fh = fopen($zip_path, 'w');
			if (FALSE === $fh) {
				throw new Exception();
			}
			$response = HTTPGETDownload($url, $fh);
			fclose($fh);

			//uncompress
			if (FALSE !== $response) {
				@exec('/usr/bin/7z x -y -o' . escapeshellarg($dir) . ' ' . escapeshellarg($zip_path));
			}
			@unlink($zip_path);

		//download a regular file
		} else {
				$fh = fopen($cache_path, 'w');
				if (FALSE === $fh) {
					throw new Exception();
				}
				// IF it is daum
				if(strstr($url,'daum') !== FALSE){
					$request=explode("/",$url);
					//preg_match('/daum\/(.*?)\/(.*?)/i',$url,$request);
					switch($request[1]){
						case "search":
							$xml = getSearch($request[2]);
							break;
						case "series":
							$xml = getSeries($request[2]);
							break;
						case "actors":
							$xml = makeXML("actors",0);
							break;
					}
					$response = fwrite($fh, $xml);
					fclose($fh);

				}
				else{
					$response = HTTPGETDownload($url, $fh);
					fclose($fh);
				}
		}

		if (FALSE === $response) {
			@unlink($cache_path);
		} else {
			$xml = GetStripedXML($cache_path);
			if (NULL === $xml) {
				$xml = FALSE;
				@unlink($cache_path);
			}
		}
	}
	return $xml;
}

/***
* daum 정보를 사용 하기 위해 한국어의 경우 url을 변경 한다.
* TVDB의 한국어 정보를 사용하기 위해 $TVDB에 설정 된 언어의 URL을 변경 한다. 
* 한국어 : 다음 , 프랑스어 : tvdb의 한국어 정보, 영어 : tvdb의 영어 정보
***/
function GetRawdata($type, $options)
{
	global $TVDB;
	$url = $cache_path = NULL;
	$zip = FALSE;
	if (0 == strcmp($type, "search")) {
		$query 		= urlencode($options['query']);
		$lang 		= $options['lang'];
		if($lang == "ko") $url = "daum/search/{$query}";
		else if($lang == $TVDB) $url = API_URL . "GetSeries.php?language=ko&seriesname={$query}";
		else $url = API_URL . "GetSeries.php?language={$lang}&seriesname={$query}";
		$cache_path = GetPluginDataDirectory(PLUGINID) . "/query/{$query}_{$lang}.xml";
	} else if (0 == strcmp($type, "series")) {
		$id			= $options['id'];
		$lang 		= $options['lang'];
		$lang2		= $lang;
		if($lang == "ko") {
			$url = "daum/series/{$id}";
		}
		else if ($lang == $TVDB) {
			$lang2 = "ko";
			$zip	= TRUE;
			$url	= API_URL . THE_TV_DB_APIKEY . "/series/{$id}/all/{$lang2}.zip";
		}
		else {
			$zip	= TRUE;
			$url	= API_URL . THE_TV_DB_APIKEY . "/series/{$id}/all/{$lang}.zip";
		}
		$cache_path = GetPluginDataDirectory(PLUGINID) . "/{$id}/{$lang}/{$lang2}.xml";
	} else if (0 == strcmp($type, "actors")) {
		$id			= $options['id'];
		$lang 		= $options['lang'];
		$lang2		= $lang;
		if($lang == "ko") {
			$url = "daum/actors/0";
		}
		else if ($lang == $TVDB) {
			$lang2 = "ko";
			$zip	= TRUE;
			$url	= API_URL . THE_TV_DB_APIKEY . "/series/{$id}/all/{$lang2}.zip";
		}
		else {
			$zip	= TRUE;
			$url	= API_URL . THE_TV_DB_APIKEY . "/series/{$id}/all/{$lang}.zip";
		}
		$cache_path = GetPluginDataDirectory(PLUGINID) . "/{$id}/{$lang}/actors.xml";
	}

	return DownloadRawdata($url, $cache_path, $zip);
}

function RemoveTrailingTag($str)
{
	$str = preg_replace('/\([^\)]+\)/ui', '', $str);
	$str = trim($str);
	return $str;
}

function ParseActors($actors_data)
{
	$actors = array();
	foreach($actors_data->Actor as $actor) {
		$actors[] = trim((string)$actor->Name);
	}
	return $actors;
}

function ParseCertificate($series_data)
{
	foreach($series_data->Series->ContentRating as $contactRating) {
		if ($contactRating) {
			return array('USA' => trim((string)$contactRating));
		}
	}
	return array();
}

function ParseTVShowData($series_data, $actors, $data)
{
	$data['title'] 				= RemoveTrailingTag(trim((string)$series_data->Series->SeriesName));
	$data['summary'] 			= trim((string)$series_data->Series->Overview);
	$data['original_available'] = trim((string)$series_data->Series->FirstAired);
	$data['certificate'] 		= ParseCertificate($series_data);

	if (0 < count($actors)) {
		$data['actor'] = $actors;
	}

	$genres = preg_split('/\s*\|\s*/', trim((string)$series_data->Series->Genre), -1, PREG_SPLIT_NO_EMPTY);
	if (0 < count($genres)) {
		$data['genre'] = array_values(array_unique($genres));
	}

	$data['extra'][PLUGINID] = array('reference' => array());
	$data['extra'][PLUGINID]['reference']['thetvdb'] = (string)$series_data->Series->id;
	if ((string)$series_data->Series->IMDB_ID) {
		$data['extra'][PLUGINID]['reference']['imdb'] = (string)$series_data->Series->IMDB_ID;
	}
	/***
	* TVDB의 경우 배너의 기본 경로를 지정 해 줘야 한다.
	* daum의 경우 xml에 Fullpath가 저장되어 있어 필요 없다.
	**/
	if ((float)$series_data->Series->Rating) {
		$data['extra'][PLUGINID]['rating'] = array('thetvdb' => (float)$series_data->Series->Rating);
	}
	if ((string)$series_data->Series->poster) {
		if(strstr($series_data->Series->poster,'http') !== false) $data['extra'][PLUGINID]['poster'] = array((string)$series_data->Series->poster);
		else $data['extra'][PLUGINID]['poster'] = array(BANNER_URL . (string)$series_data->Series->poster);
	}
	if ((string)$series_data->Series->fanart) {
		if(strstr($series_data->series->poster,"http") !== false) $data['extra'][PLUGINID]['backdrop'] = array((string)$series_data->Series->fanart);
		else $data['extra'][PLUGINID]['backdrop'] = array(BANNER_URL . (string)$series_data->Series->fanart);
	}

	return $data;
}

function ParsedataSet($series_data, $actors, $episode_data, $data)
{
	$data['season'] 			= (int)$episode_data->SeasonNumber;
	$data['episode'] 			= (int)$episode_data->EpisodeNumber;
	$data['tagline'] 			= trim((string)$episode_data->EpisodeName);
	$data['original_available'] = trim((string)$episode_data->FirstAired);
	$data['summary'] 			= trim((string)$episode_data->Overview);
	$data['certificate'] 		= ParseCertificate($series_data);

	if (0 < count($actors)) {
		$data['actor'] = $actors;
	}

	$writers = preg_split('/\s*\|\s*/', trim((string)$episode_data->Writer), -1, PREG_SPLIT_NO_EMPTY);
	if (0 < count($writers)) {
		$data['writer'] = array_values(array_unique($writers));
	}

	$directors = preg_split('/\s*\|\s*/', trim((string)$episode_data->Director), -1, PREG_SPLIT_NO_EMPTY);
	if (0 < count($directors)) {
		$data['director'] = array_values(array_unique($directors));
	}

	$genres = preg_split('/\s*\|\s*/', trim((string)$series_data->Series->Genre), -1, PREG_SPLIT_NO_EMPTY);
	if (0 < count($genres)) {
		$data['genre'] = array_values(array_unique($genres));
	}

	$data['extra'][PLUGINID]['reference'] = array();
	$data['extra'][PLUGINID]['reference']['thetvdb'] = (string)$episode_data->id;
	if ((string)$episode_data->IMDB_ID) {
		 $data['extra'][PLUGINID]['reference']['imdb'] = (string)$episode_data->IMDB_ID;
	}
	if ((float)$episode_data->Rating) {
		$data['extra'][PLUGINID]['rating'] = array('thetvdb' => (float)$episode_data->Rating);
	}
	if ((string)$episode_data->filename) {
		$data['extra'][PLUGINID]['poster'] = array(BANNER_URL . (string)$episode_data->filename);
	}

	return $data;
}

function SeasonCompare($a, $b)
{
    if ($a['season'] == $b['season']) {
        return 0;
    }

    return ($a['season'] < $b['season']) ? -1 : 1;
}

function EpisodeCompare($a, $b)
{
    if ($a['episode'] == $b['episode']) {
        return 0;
    }
    return ($a['episode'] < $b['episode']) ? -1 : 1;
}

/**
 * @brief this is a auxiliary list that can help sort the
 *  	  episodes by season number and episode number.
 * @param $item [in] a episode item
 * @param $list [in, out] a json format. [['season' => 1,
 *  			'episode' => [<episode 1>, <episode 2>,
 *  			...]],[['season' => 2,'episode' => [<episode 1>,
 *  			<episode 2>,...]],...]
 */
function InsertItemToList($item, &$list)
{
    $found = false;

    foreach ($list as $key => $value) {
        if ($value['season'] == $item['season']) {
            $found = true;
            break;
        }
    }

    if ($found) {
        $list[$key]['episode'][] = $item;
    } else {
        $list[] = array(
            'season' => $item['season'],
            'episode' => array($item));
    }
}

function SortList(&$list)
{
	uasort($list, 'SeasonCompare');
	$list = array_values($list);

	foreach($list as $key => &$value) {
		uasort($value['episode'], 'EpisodeCompare');
		$value['episode'] = array_values($value['episode']);
	}
}

/**
 * @brief get tvshow information
 * @param $series_data [in] series rawdata
 * @param $actors [in] a array contains actor names
 * @param $data [in] a metadata json object
 * @return [out] a metadata json object
 */
function GetTVShowInfo($series_data, $actors, $data)
{
	//Fill tvshow information
	$data = ParseTVShowData($series_data, $actors, $data);

	//Fill all episode information
	$list = array();
	foreach ($series_data->Episode as $item) {
		$item = ParsedataSet($series_data, $actors, $item, array());
		InsertItemToList($item, $list);
	}
	SortList($list);
	$data['extra'][PLUGINID]['list'] = $list;

	return $data;
}

/**
 * @brief get episode information
 * @param $series_data [in] series rawdata
 * @param $actors [in] a array contains actor names
 * @param $season [in] season number
 * @param $episode [in] episode number
 * @param $data [in] a metadata json object
 * @return [out] a metadata json object
 */
function GetEpisodeInfo($series_data, $actors, $season, $episode, $data)
{
	$episode_data = FALSE;

	//Get episode data
	foreach ($series_data->Episode as $item) {
		if ($season == $item->SeasonNumber ||
			(NULL === $season && 1 == $item->SeasonNumber)) {
			if ($episode == $item->EpisodeNumber) {
				$episode_data = $item;
				break;
			}
		}
	}

	//Fill tvshow information
	$data['title'] = RemoveTrailingTag(trim((string)$series_data->Series->SeriesName));
	$data['extra'] = array(PLUGINID => array());
	$data['extra'][PLUGINID]['tvshow'] = ParseTVShowData($series_data, $actors, array());

	//Fill episode information
	if ($episode_data) {
		$data = ParsedataSet($series_data, $actors, $episode_data, $data);
	}

	return $data;
}

/**
 * @brief get metadata for multiple movies
 * @param $query_data [in] a array contains multiple movie item
 * @param $season [in] season number
 * @param $episode [in] episode number
 * @param $lang [in] a language
 * @param $type [in] tvshow, tvshow_episode
 * @return [out] a result array
 */
function GetMetadata($query_data, $season, $episode, $lang, $type)
{
	global $DATA_TEMPLATE;

	//Foreach query result
	$result = array();
	foreach($query_data as $item) {
		
		/***
		* TVDB의 한국어 데이터를 살리기 위해 언어가 다를 경우 skip 하는 것을 skip 함
		***/

		//If languages are different, skip it
		//if (0 != strcmp($item['lang'], $lang)) {
		//	continue;
		//}

        //Copy template
		$data = $DATA_TEMPLATE;

		//Get series
		//TVDB의 한국어 데이터를 살리기 위해 언어 설정을 변경함

		//$series_data = GetRawdata("series", array('id' => $item['id'], 'lang' => $item['lang']));
		$series_data = GetRawdata("series", array('id' => $item['id'], 'lang' => $lang));
		
		if (!$series_data) {
			continue;
		}

		//Get actors
		//TVDB의 한국어 데이터를 살리기 위해 언어 설정을 변경함
		//$actors_data = GetRawdata("actors", array('id' => $item['id'], 'lang' => $item['lang']));
		$actors_data = GetRawdata("actors", array('id' => $item['id'], 'lang' => $lang));
		$actors = ParseActors($actors_data);

		switch ($type) {
			case 'tvshow':
				$data = GetTVShowInfo($series_data, $actors, $data);
				break;
			case 'tvshow_episode':
				$data = GetEpisodeInfo($series_data, $actors, $season, $episode, $data);
				break;
		}

		//Append to result
		$result[] = $data;
	}

	return $result;
}

function Query($query, $year, $lang, $limit)
{
	$result = array();

	//Get search result
	$search_data = GetRawdata('search', array('query' => $query, 'lang' => $lang));
	if (!$search_data) {
		return $result;
	}

	//Get all items
	foreach($search_data->Series as $item) {
		$data = array();
		$data['id'] 	= (string)$item->seriesid;
		$data['lang'] 	= (string)$item->language;
		$data['diff'] = 1000;
		if (isset($item->FirstAired)) {
			$item_year = ParseYear((string)$item->FirstAired);
			$data['diff'] = abs($item_year - $year);
		}
		//DAUM 데이터를 사용하기 위해 아래 코드 skip
		/* 
		if ($year && $data['diff'] >= 2) {
			continue;
		}
		 */
		$result[] = $data;
	}

	//If no result
	if (!count($result)) {
		return $result;
	}

	//Get the first $limit items
	$result = array_slice($result, 0, $limit);

	return $result;
}

function Process($input, $lang, $type, $limit, $search_properties, $allowguess, $id)
{
	$result = array();

	$title 	 = $input['title'];
	$year 	 = ParseYear($input['original_available']);
	$lang 	 = ConvertToAPILang($lang);
	$season  = $input['season'];
	$episode = $input['episode'];
	if (!$lang) {
		return array();
	}

	if (0 < $id) {
		// if haved id, output metadata directly.
		return GetMetadata(array(array('id' => $id, 'lang' => $lang)), $season, $episode, $lang, $type);
	}

	//year
	if (isset($input['extra']) && count($input['extra']) > 0) {
		$pluginid = array_shift($input['extra']);
		if (!empty($pluginid['tvshow']['original_available'])) {
			$year = ParseYear($pluginid['tvshow']['original_available']);
		}
	}

	//Search
	//비디오스테이션 자체 검색 방법 skip
	$allowguess = false;
	$query_data = array();
	$titles = GetGuessingList($title, $allowguess);
	foreach ($titles as $checkTitle) {
		if (empty($checkTitle)) {
			continue;
		}
		$query_data = Query($checkTitle, $year, $lang, $limit);
		if (0 < count($query_data)) {
			break;
		}
	}

	//Get metadata
	return GetMetadata($query_data, $season, $episode, $lang, $type);
}

PluginRun('Process');
?>
