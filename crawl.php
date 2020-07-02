<?php

include("connect.php");
include("classes/domDocumentParser.php");

$alreadyCrawled = array();
$crawling = array();
$alreadyFoundImages = array();

    function linkExists($url){
        global $conn ;
        $query = "select * from sites where url = '$url'";

        $result = $conn->query($query);
        $num = $result->num_rows ;
        return $num!=0 ;
        
    }

    function insertLink($url,$title,$description,$keywords){
        global $conn ;
        $query = "insert into sites(title,url,description,keywords) 
                  values('$title','$url','$description','$keywords')" ;

        $result = $conn->query($query);
        return $result ;
        
    }

    function insertImage($url,$src,$alt,$title){
        global $conn ;

        $sql = "select * from images where imageUrl = '$src' " ;
        $checkResult = $conn->query($sql);
        $checkNum = $checkResult->num_rows ;
        
        if($checkNum == 0){
            $query = "insert into images(siteUrl,imageUrl,alt,title) 
                  values('$url','$src','$alt','$title')" ;

            $result = $conn->query($query);
            return $result ;
        }

    }

    function craeteLinks($src, $url){
        $scheme = parse_url($url)["scheme"] ;  // http
        $host = parse_url($url)["host"] ;    // www.google.com
        
        if(substr($src,0,2) == "//"){
            $src = $scheme . ":" . $src ;
        }
        else if(substr($src,0,1) == "/"){
            $src = $scheme . "://" . $host . $src ;
        }
        else if(substr($src,0,2) == "./"){
            $src = $scheme . "://" . $host . dirname(parse_url($url)["path"]) . substr($src,1) ;
        }
        else if(substr($src,0,3) == "../"){
            $src = $scheme . "://" . $host . "/" . $src ;
        }
        else if(substr($src,0,5) != "https" && substr($src,0,4) != "http"){
            $src = $scheme . "://" . $host . "/" . $src ;
        }
        return $src ;
    }

    function getDetails($url){
        global $alreadyFoundImages;

        $parser = new DomDocumentParser($url);
        $titleArray = $parser->getTitleTags();

        if(sizeof($titleArray) == 0 || $titleArray->item(0) == NULL){
            return ;
        }

        $title = $titleArray->item(0)->nodeValue ;
        $title = str_replace("\n","",$title);

        if($title == ""){
            return;
        }

        $description = "" ;
        $keywords = "" ;

        $metasArray = $parser->getMetaTags();

        foreach($metasArray as $meta){
            if($meta->getAttribute("name") == "description"){
                $description = $meta->getAttribute("content");
            }
            if($meta->getAttribute("name") == "keywords"){
                $keywords = $meta->getAttribute("content");
            }
        }

        $description = str_replace("\n","",$description);
        $keywords = str_replace("\n","",$keywords);

        if(linkExists($url)){
            echo "$url already exist <br>" ;
        }
        else if(insertLink($url, $title, $description, $keywords)){
            echo "SUCCESS : $url <br>" ;
        }
        else {
            echo "ERROR! Failed to insert URL" ;
        }

        $imageArray = $parser->getImages();
        foreach($imageArray as $image){
            $src = $image->getAttribute("src");
            $alt = $image->getAttribute("alt");
            $title = $image->getAttribute("title");

            if($title == "" && $alt == ""){
                continue;
            }

            $src = craeteLinks($src,$url);

            if(!in_array($src,$alreadyFoundImages)){
                $alreadyFoundImages[] = $src ;

                insertImage($url,$src,$alt,$title);
            }
        }
        
    }

    function followLinks($url){

        global $alreadyCrawled;
        global $crawling ;

        $parser = new DomDocumentParser($url);

        $linkList = $parser->getLinks();

        foreach($linkList as $links){
            $href = $links->getAttribute("href");

            if(strpos($href, "#") !== false){
                continue;
            }
            else if(substr($href,0,11) == "javascript:"){
                continue ;
            }

            $href = craeteLinks($href, $url);

            if(!in_array($href,$alreadyCrawled)){
                $alreadyCrawled[] = $href; 
                $crawling[] = $href ;

                getDetails($href);
            }
            // else {
            //     return ;
            // }

            
        }

        array_shift($crawling);
        foreach ($crawling as $site) {
            followLinks($site);
        }
    }

    $startUrl = "https://www.w3schools.com/";
    followLinks($startUrl);
?>