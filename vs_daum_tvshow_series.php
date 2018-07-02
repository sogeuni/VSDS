<?
/***
* 파일의 영상 제목을 이용 해 daum에서 기본 정보를 검색 한다.
* Video Station에서 읽어들이는 XML 형식으로 정보를 가공한다.
*/

$keyword=$_GET['search'];
$seriesurl="http://movie.daum.net/data/movie/search/v2/tv.json?size=20&start=1&searchText=";
$dataSet=array();
getSeries($seriesurl,$keyword);
makeXML();

function makeXML(){
	global $dataSet;
	$xml = "<?xml version='1.0' encoding='UTF-8'?>";
	$xml .= "<Data>";
	for($i=0;$i<count($dataSet);$i++){
		$xml .= "<Series>";
		$xml .= "<seriesid>".$dataSet[$i]["id"]."</seriesid>";
		$xml .= "<id>".$dataSet[$i]["id"]."</id>";
		$xml .= "<FirstAired>".$dataSet[$i]["FirstAired"]."</FirstAired>";
		$xml .= "<Genre>".$dataSet[$i]["Genre"]."</Genre>";
		$xml .= "<language>".$dataSet[$i]["Language"]."</language>";
		$xml .= "<Network>".$dataSet[$i]["Network"]."</Network>";
		$xml .= "<Overview>".$dataSet[$i]["Overview"]."</Overview>";
		$xml .= "<SeriesName>".$dataSet[$i]["SeriesName"]."</SeriesName>";
		$xml .= "<poster>".$dataSet[$i]["poster"]."</poster>";
		$xml .= "</Series>";
	}
	$xml .= "</Data>";
	header('Content-type: text/xml');
	echo $xml;
	return $xml;
}

function getSeries($url,$keyword){
	global $dataSet;
	$rawdata=json_decode(file_get_contents($url.urlencode($keyword)),true);
	for($i=0;$i<$rawdata["count"];$i++){
		$year=substr($rawdata["data"][$i]["startDate"],0,4);
		$month=substr($rawdata["data"][$i]["startDate"],4,2);
		$day=substr($rawdata["data"][$i]["startDate"],6,2);
		$dataSet[$i]["id"]=$rawdata["data"][$i]["tvProgramId"];
		$dataSet[$i]["FirstAired"]=$year."-".$month."-".$day;
		$dataSet[$i]["Genre"]=$rawdata["data"][$i]["genres"][0]["genreName"];
		switch($rawdata["data"][$i]["countries"][0]["countryko"]){
			case "대한민국":
				$dataSet[$i]["Language"]="ko";
				break;
			case "미국":
				$dataSet[$i]["Language"]="en";
				break;
			case "중국":
				$dataSet[$i]["Language"]="cn";
				break;
			case "일본":
				$dataSet[$i]["Language"]="jp";
				break;
			default:
				$dataSet[$i]["Language"]="ko";
		}
		$dataSet[$i]["Network"]=$rawdata["data"][$i]["channel"]["titleKo"];
		$dataSet[$i]["Overview"]="-";
		$dataSet[$i]["SeriesName"]=$rawdata["data"][$i]["titleKo"];
		$dataSet[$i]["poster"]=$rawdata["data"][$i]["photo"]["fullname"];
	}
}
?>