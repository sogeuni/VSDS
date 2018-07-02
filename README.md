# VSDS

VideoStation에서 한국어 방송 정보를 보다 잘 사용할 수 있도록 합니다.

VideoStation에서 Cache한 데이터를 모두 삭제 후 사용하는 것을 권장 합니다.  
자세한 Cache 데이터 삭제 방법은 본문 하단 참조 바랍니다.

### 1. 파일 설치

* search.php 

이 파일은 VideoStation이 tvdb에서 필요한 정보를 찾아서 방송 정보를 가져오는 파일입니다. 
이 파일을 고쳐서 daum과 tvdb 중 원하는 정보를 쓸 수 있도록 했습니다.
search.php 파일을 기존 tvdb의 경로에 복사 해 넣습니다. 기존 search.php를 대체합니다.
기존 search.php 파일의 경로는 /volume1/@appstore/VideoStation/plugins/syno_thetvdb/ 입니다. 
사용자 설정에 따라 경로가 바뀔 수 있으니 본인 환경을 확인해서 찾아 봅니다.

* vs_daum_tvshow_series.php , vs_daum_tvshow_episode.php, vs_daum_tvshow_actor.php

위 파일들은 daum에서 필요한 정보를 찾아오는 역할을 합니다. 최소한의 작업만 하기 위해 tvdb처럼 웹에서 작동하도록 했습니다. 
시놀로지는 손 쉽게 웹서버를 띄울 수 있으니 웹서버 작동시키고 웹서버에 3개 파일을 올려 놓습니다. 
웹서버에 올린 파일이 잘 동작하는지 테스트 하려면 브라우저에 아래와 같이 입력해 봅니다.

http://localhost/vs_daum_tvhshow_search.php?search="방송제목" 

localhost는 본인의 웹서버 주소입니다. 시놀로지 기본 웹서버에 올리시면 localhost를 사용할 수 있습니다.

### 2. 환경 설정

#### 1) Daum과 TVDB 정보 선택 하기

VideoStation 인터페이스를 수정하려면 공사가 크기 때문에 '비디오 정보 검색 언어'를 한국어로 설정하면 Daum을 이용하고 다른 언어를 선택하면 tvdb를 사용하도록 했습니다. 
tvdb의 한국어 정보를 이용하고 싶을 때는 프랑스어를 선택합니다.  
이런식으로 정보를 가져온다고 보시면 이해가 쉽습니다.

>한국어 : Daum 한국어 정보  
>프랑스어 : TVDB 한국어 정보  
>영어 : TVDB 영어 정보  
>일본어 : TVDB 일본어 정보  

search.php 상단의 $TVDB 변수를 바꾸면 프랑스가 아닌 원하는 국가로 변경할 수 있습니다. 
예를들어 $TVDB=nl로 설정하면 네덜란드어 선택 시 TVDB의 한국어 정보를 가져오게 됩니다.  



#### 2) 웹서버 경로 변경

기본적으로 localhost로 작동하니 localhost를 이용할 경우 변경이 필요 없습니다.
search.php 파일 상단에 $DAUMURL을 본인의 웹서버로 변경합니다. 주의하실 점은 끝에 "/"를 꼭 넣으셔야 합니다.


#### 3) VideoStation Library 추가
자 이제 환경 설정도 끝났으니 VideoStation에서 한국어로 된 라이브러리 구축 해 보시기 바랍니다.

### 3. VideoStation Cache 데이터 정리
VideoStation은 캐시 데이터 이용이 많습니다. VSDS 사용 전에 캐시 정리를 추천합니다. 
정리가 안되면 이전 정보를 계속 가져오게 됩니다. 

#### 1) vsmeta 파일 삭제

VideoStation은 라이브러리 정보를 수정할 때 변경 된 정보를 저장하는 vsmeta파일을 생성합니다. 해당 파일을 전부 찾아 삭제 해야 합니다.

>ex. find /영상저장경로 -name *.vsmeta -exec rm -f {}\;


#### 2) tvdb 캐시 데이터를 삭제 합니다. 아래 경로 에 있습니다.

/volume1/@appstore/VideoStation/plugins/plugin_data/com.synology.TheTVDB 

>ex. rm -rf * 


#### 3) VideoStation DB 삭제 (기존 라이브러리 정보 사라집니다)
ssh 접속 후 아래와 같이 postgres 내 video_metadata DB를 삭제 합니다.
>ex.  
>sudo -i  
>su postgres  
>psql  
>drop database video_metadata  


### 4. VideoStation에서 라이브러리 정리하는 

daum은 "방송이름 시즌3" 이렇게 파일명을 잡아 주면 제대로 된 정보를 가져오는 반면 "방송이름.s03e00" 이렇게 하면 시즌 정보를 인식하지 못합니다. 
tvdb는 반대로 "방송이름 시즌3"이라고 하면 인식하지 못합니다. 
따라서 한국방송은 시즌을 파일명에 넣고 daum을 사용하고 외국방송은 tvdb를 사용하면 비교적 만족스러운 결과를 얻을 수 있습니다.
