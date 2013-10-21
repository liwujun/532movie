<?php

define("APIURL","http://api.douban.com/v2/movie/[id]"); //apiҳ��
define("MOVIEURL","http://movie.douban.com/subject/[id]/"); //��Ӱҳ��
define("POSTERURL","http://movie.douban.com/subject/[id]/photos?type=R"); //����ҳ��
define("STILLSURL","http://movie.douban.com/subject/[id]/photos"); //����ҳ��

define("PICURL","http://img3.douban.com/view/photo/photo/public/p[pid].jpg"); //ͼƬ��ַ
define("TRAILERURL","http://movie.douban.com/trailer/video_url?tid=[tid]"); //Ԥ��Ƭ��ַ

class AddAction extends HomeAction{
    private $id;
    private $apijson;
    private $title;   //Ƭ��
    private $point;   //����
    private $numRaters; //��������
    private $summary; //������
    private $language; //����
    private $pubdate; //��ӳ����
    private $country; //��Ƭ����/����
    private $writer;  //���
    private $director; //����
    private $cast;     //����
    private $duration; //Ƭ��
    private $year;     //���
    private $type;    //����
    private $tags;    //��ǩ
    private $savepath; //���·��
    private $poster_path;   //��Ӱ������ַ
    private $trailer_path;  //��ӰԤ��Ƭ��ַ
    private $pics_path;     //���ຣ����ַ֮����,�������ַ���
    private $stills_path;   //������յ�ַ֮����,�������ַ���
    private $letter;  //Ƭ������ĸ
    private $cid; //ӰƬ����
    private $bt_path;  //BT����·��

    public function index(){

        $this->display('add');
    }
    public function action(){

        set_time_limit(0);
        //ini_set('memory_limit','1024M'); 
        ignore_user_abort(true);  
        $this->id=$_POST['id'];
        $this->savepath = 'uploads/video/'.($this->id).'/';
        
        $this->bt_path = $this->upload();
        if(!$this->bt_path)  $this->error('Upload Fail!');
        else{
            $this->getinfo();
            $this->letter=$this->getFirstchar($this->title);
            $this->cid=$this->getcid($this->type);
            $this->getmedia();
            $this->pics_path=$this->getpic(0);
            $this->stills_path=$this->getpic(1);
            $tag=$this->insert_into_db();
            if($tag){
               // $this->display('add');
                $this->success('Success!');
            } 
            else if($tag==-1) $this->error('Film Exists');
            else $this->error('Fail!');
        }
    }
    private function upload(){
        import("ORG.Net.UploadFile");
        $upload = new UploadFile();
        $upload->allowExts  = array('torrent');
        $upload->savePath = $this->savepath;
        if(!$upload->upload()){
            return false; 
        }else{
            $uploadList=$upload->getUploadFileInfo();
            return ($upload->savePath).$uploadList[0]['savename'];
        }
    }

    private function getinfo(){
        $url=str_replace("[id]",$this->id,APIURL);
        $this->apijson=$this->urlopen($url);
        if(!($this->apijson)){
            echo "��ȡӰƬ��Ϣ����";
            return;
        }
        @$obj=json_decode($this->apijson);
        @$attrs=$obj->attrs;

        @$alt_title=$obj->alt_title;
        @$alt_title=explode('/',$alt_title);
        @$title=$attrs->title;
        @$title=array_unique(array_merge($alt_title,$title));
        @$this->title=$this->split_array($title);

        @$this->point=$obj->rating->average;

        @$this->numRaters=$obj->rating->numRaters;

        @$summary=$obj->summary;
        @$summary=htmlentities($summary,ENT_COMPAT,'UTF-8');
        @$summary="&nbsp;&nbsp;&nbsp;&nbsp;".str_replace("\n","\n&nbsp;&nbsp;&nbsp;&nbsp;",$summary);
        @$summary=nl2br($summary);
        @$this->summary=$summary;

        @$this->language =$this->split_array($attrs->language);
        @$this->pubdate  =$this->split_array($attrs->pubdate);
        @$this->country  =$this->split_array($attrs->country);
        @$this->writer   =$this->split_array($attrs->writer,1);
        @$this->director =$this->split_array($attrs->director,1);
        @$this->cast     =$this->split_array($attrs->cast,1);
        @$this->duration =$this->split_array($attrs->movie_duration);
        @$this->year     =$this->split_array($attrs->year);
        @$this->type     =$this->split_array($attrs->movie_type);
        
        @$tags=$obj->tags;
        foreach($tags as $i) $this->tags.=$i->name."/";
        $this->tags=substr($this->tags,0,strlen($this->tags)-1);

    }
    //����תΪ�ַ���
    private function  split_array($ar,$tag=0){
        
        if(gettype($ar)=="array"){
            foreach ($ar as $key => $i) {
                if($tag==1){
                    $i=explode(" ", $i);
                    $i=$i[0];
                } 
                $ret.=$i."/";
            }
            return substr($ret,0,strlen($ret)-1); 
        }else{
            return "δ֪";
        }
    }

    //������ҳ������Ԥ��Ƭ
     private function getmedia(){
        
        //�����Ӱҳ��
        $url=str_replace("[id]",$this->id,MOVIEURL);
        $content=$this->urlopen($url);
        if(!$content) return false;

        //������ҳ����
        $regex='#<img src="http://img3.douban.com/view/photo/thumb/public/p([0-9]*).jpg" title#'; 
        if(preg_match($regex, $content, $matches)){
            //print_r($matches); 
            $pid=$matches[1];
            if( !empty($pid) ){
                $url=str_replace("[pid]",$pid,PICURL);
                $path=$this->savepath.$pid.".jpg";
                if($this->urlretrieve($url,$path)) $this->poster_path=$path;
            }
        }else{
                             //http://img5.douban.com/view/movie_poster_cover/spst/public/p1935067049.jpg
            $regex='#<img src="http://img5.douban.com/view/movie_poster_cover/spst/public/p([0-9]*).jpg" title#'; 
            if(preg_match($regex, $content, $matches)){
                $pid=$matches[1];
                if( !empty($pid) ){
                    $url=str_replace("[pid]",$pid,PICURL);
                    $path=$this->savepath.$pid.".jpg";
                    if($this->urlretrieve($url,$path)) $this->poster_path=$path;
                }
            }
        }

        //����Ԥ��Ƭ
        $regex='#<a class="related-pic-video" href="http://movie.douban.com/trailer/([0-9]*)/#'; 
        if(preg_match($regex, $content, $matches)){
            //print_r($matches);
            $tid=$matches[1];
            if( !empty($tid) ){
                $url=str_replace("[tid]",$tid,TRAILERURL);
                $headers=get_headers($url,true);
                //print_r($headers);
                $url=$headers['Location'];
                if(!empty($url)){
                    $path=$this->savepath.strrchr($url,'/');
                    if( $this->urlretrieve($url,$path) ){
                       $this->trailer_path=$path;
                    }
                }
            }
        } 
    }

    //���غ���(0)�����(1)��һҳ
    private function getpic($ptype){
        if($ptype==0) $url=str_replace("[id]",$this->id,POSTERURL); //����
        else $url=str_replace("[id]",$this->id,STILLSURL); //����
        $content=$this->urlopen($url);
        if(!$content) return false;
        $regex='#<img src="http://img3.douban.com/view/photo/thumb/public/p([0-9]*).jpg" /></a></div>#';
        $ret="";
        if(preg_match_all($regex, $content, $matches)){
            //print_r($matches);
            if(empty($matches)) return;
            
            foreach($matches[1] as $pid){
                if(empty($pid)) continue;
                $url=str_replace("[pid]",$pid,PICURL);
                $path=$this->savepath.$pid.".jpg";
                if( $this->urlretrieve($url,$path) ){
                    if(empty($this->poster_path)) $this->poster_path=$path;
                    $ret.=$path.",";      
                }
            }
        }
        if( strlen($ret)>0 ) $ret=substr($ret,0,strlen($ret)-1);
        return $ret;
    }

    //���ӰƬ��Ϣ
    public function outputinfo(){
        header("Content-type:text/html; charset=utf-8");
        echo "<html><head></head><body>";

        echo "<h2>Ƭ��</h2>".$this->title;
        echo "<h2>����</h2>".$this->point;
        echo "<h2>��������</h2>".$this->numRaters;
        echo "<h2>������</h2>".$this->summary;
        echo "<h2>����</h2>".$this->language;
        echo "<h2>��ӳ����</h2>".$this->pubdate;
        echo "<h2>��Ƭ����/����</h2>".$this->country;
        echo "<h2>���</h2>".$this->writer;
        echo "<h2>����</h2>".$this->director;
        echo "<h2>����</h2>".$this->cast;
        echo "<h2>Ƭ��</h2>".$this->duration;
        echo "<h2>���</h2>".$this->year;
        echo "<h2>����</h2>".$this->type;
        echo "<h2>��ǩ</h2>".$this->tags;
        echo "<hr/>";
        echo "<h2>����</h2>";
        echo "<img src='". $this->poster_path ."'/>";
        echo "<a href=' ".$this->trailer_path ."'> Ԥ��Ƭ </a>";

        echo "<h2>���ຣ��</h2>";
        $ar=explode(',',$this->pics_path);
        foreach ($ar as $i) echo "<img src=' $i '/>";
        
        echo "<h2>����</h2>";
        $ar=explode(',',$this->stills_path);
        foreach ($ar as $i) echo "<img src=' $i '/>";

        echo "</body></html>";
    }

    //����ҳ��html��urlopen����
    private function urlopen($url){
        set_time_limit(0);
         @$stream=fopen($url,'rb');
        if(!$stream) return false;
        $content=stream_get_contents($stream);
        @fclose($stream);
        return $content;
    }

    //������ҳ�ļ���urlretrieve����
    private function urlretrieve($url,$path){
        set_time_limit(0);
        if(file_exists($path)) return true;
        @$stream=fopen($url,"rb");
        if(!$stream) return false;
        @$fp = fopen($path,"wb");
        if(!$fp) return false;
        while(!feof($stream)){
            @fwrite($fp,fgets($stream,1024));
        }
        @fclose($stream);
        @fclose($fp);
        return true; 
    }
    private function getFirstChar($str){
        @$str=iconv("UTF-8","gb2312",$str);
        $asc=ord(substr($str,0,1));
        if ($asc<160){ //������
            if ($asc>=48 && $asc<=57){
                return chr($asc); //����
            }elseif ($asc>=65 && $asc<=90){
                return chr($asc);    // A--Z
            }elseif ($asc>=97 && $asc<=122){
                return chr($asc-32); // a--z
            }else{
                return '~'; //����
            }
        }
        else{ //����
            $asc=$asc*1000+ord(substr($str,1,1));
            //��ȡƴ������ĸA--Z
            if ($asc>=176161 && $asc<176197){
                return 'A';
            }elseif ($asc>=176197 && $asc<178193){
                return 'B';
            }elseif ($asc>=178193 && $asc<180238){
                return 'C';
            }elseif ($asc>=180238 && $asc<182234){
                return 'D';
            }elseif ($asc>=182234 && $asc<183162){
                return 'E';
            }elseif ($asc>=183162 && $asc<184193){
                return 'F';
            }elseif ($asc>=184193 && $asc<185254){
                return 'G';
            }elseif ($asc>=185254 && $asc<187247){
                return 'H';
            }elseif ($asc>=187247 && $asc<191166){
                return 'J';
            }elseif ($asc>=191166 && $asc<192172){
                return 'K';
            }elseif ($asc>=192172 && $asc<194232){
                return 'L';
            }elseif ($asc>=194232 && $asc<196195){
                return 'M';
            }elseif ($asc>=196195 && $asc<197182){
                return 'N';
            }elseif ($asc>=197182 && $asc<197190){
                return 'O';
            }elseif ($asc>=197190 && $asc<198218){
                return 'P';
            }elseif ($asc>=198218 && $asc<200187){
                return 'Q';
            }elseif ($asc>=200187 && $asc<200246){
                return 'R';
            }elseif ($asc>=200246 && $asc<203250){
                return 'S';
            }elseif ($asc>=203250 && $asc<205218){
                return 'T';
            }elseif ($asc>=205218 && $asc<206244){
                return 'W';
            }elseif ($asc>=206244 && $asc<209185){
                return 'X';
            }elseif ($asc>=209185 && $asc<212209){
                return 'Y';
            }elseif ($asc>=212209){
                return 'Z';
            }else{
                return '~';
            }
        }
    }
    private function getcid($type){
        if(stristr($type,'����')||stristr($type,'����')) return 3;
        if(stristr($type,'����')||stristr($type,'��̸')) return 4;
        if(stristr($type,'����')) return 5;
        if(stristr($type,'��¼')||stristr($type,'дʵ')) return 6;

        if(stristr($type,'�ƻ�')||stristr($type,'���')) return 11;
        if(stristr($type,'����')||stristr($type,'ð��'))return 8;
        if(stristr($type,'�ֲ�')||stristr($type,'���')) return 13;
        if(stristr($type,'ս��')||stristr($type,'����')) return 14;
        if(stristr($type,'����'))return 10;
        if(stristr($type,'ϲ��')) return 9;
        return 12; //����
    }
    private function insert_into_db(){
        $video=M('video');
         
        //ӰƬ���� 
        $id=$this->id;
        $tag=$video->where(" doubanid=' $id ' ")->find();
        if( !empty($tag) ) return -1;
        $data=array();

        $data['doubanid']=$this->id;
        $data['cid']=$this->cid;
        $data['title']=$this->title;
        $data['keywords']=$this->tags;
        $data['actor']=$this->cast;
        $data['director']= $this->director ;
        $data['writer']= $this->writer ;
        $data['content']=$this->summary;
        $data['picurl']=substr($this->poster_path,8);
        $data['area']=$this->country;
        $data['language']=$this->language;        
        $data['year']=$this->year;
        $data['duration']=$this->duration;
        $data['addtime']=time();
        $data['stars']=(int)(($this->point + 0.4)/2);
        $data['playurl']=$this->bt_path ;
        $data['downurl']= $this->bt_path ;
        $data['inputer']='wulang';
        $data['letter']= $this->letter;
        $data['score']=$this->point ;
        $data['scoreer']=$this->numRaters;
        $data['trailer']= $this->trailer_path ;
        $data['posters']= $this->pics_path ;
        $data['stills']= $this->stills_path ;
        return $video->add($data);
    }

}
?>
