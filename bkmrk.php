<?php

/*
 * bkmrk.php:		A quick & personal web bookmarks system akin to del.icio.us.
 * author:			Kevin Richardson <kevin@magically.us>
 * last update:		26-Oct-2011 
 * Github repo:		https://github.com/kfredrichardson/bkmrk
 * 
 * --
 * License:  [The MIT-Zero License]
 * See LICENSE
 * --
 * 
 * [USAGE:  bkmrk.php?...]
 *
 *		init				creates bkmrk tables in sqlite3 database $dbname. Disabled if $enable_init = false
 *
 *		import?file			del.icio.us xml bookmarks file, relative to bkmrk.php, to import.  see http://bit.ly/hUAAak
 *		export				exports delicious-like XML file of bookmarks
 *
 * 		get&				by default, returns $def_num most recent links
 *				num			max number of posts returned.  replaces $def_num
 *				id			specific post ID to return
 *				tag			specific tags to include in query.  ex: get&tag=tag1,tag2...
 *				rss			returns RSS2.0 feed of query
 *
 *		post&				used to post a new link through GET method.  If this value does not equal "post link", it creates page to post/edit links
 *				id			id of already existing post to modify
 *				uri			uri to save
 *				title		title of page
 *				desc		quick description/note about page
 *				tag			list of comma-separated tags to attach to post
 *		delete&
 *				id=			deletes post number id
 *
 * --
 */
 
/** <SETTINGS> **/ 
// file to use for sqlite3 database
$dbname = "/home/kevin/playground/bkmrk.db";
// enables creation of bkmrk tables.  change to FALSE after running ?init
$enable_init = false;
// enables import of del.icio.us bookmarks through its API's XML file.  See usage.
$enable_import = false;
// number of posts to return if num is not specified in query.
$def_num = 10;
// date format, using php's date()
$date_format = "d-M-y @  G i";
// timezone setup (see http://us2.php.net/manual/en/timezones.php)
date_default_timezone_set("US/Eastern");
/** </SETTINGS>	**/

/** setup the database connection **/
if(isset($_GET['init']) && $enable_import){ $base = new SQLite3($dbname, SQLITE3_OPEN_CREATE); }
else{ $base = new SQLite3($dbname, SQLITE3_OPEN_READWRITE); }

// printing class using templates
class Template {
	protected $type;
	protected $text;
	
	// creates a Template which will be printed out later.  valid types:  html (default), rss, xml (for exportation purposes)
	public function __construct($type = "html"){
		$this->type = $type;
		$this->text = "";
	}
	
	// returns type of Template
	public function getType(){
		return $this->type;
	}
	
	// adds header to Template
	public function addHeader($tags = "", $num = 10){
		if($this->type == "html"){
			$temp = file_get_contents("views/htmlheader.htm");
         	$tag_string = "";

            if($tags != ""){
                foreach($tags as $tag){
                    $tag_string .= $tag . ",";
                }
            }
   
            $tag_string = substr($tag_string, 0, strlen($tag_string) - 1);
            $temp = str_replace("#TAGS#", $tag_string, $temp);

			$temp = str_replace("#NUM#", $num, $temp);
		}
		elseif($this->type == "rss"){ $temp = file_get_contents("views/rssheader.xml") . "\n\r"; }
		elseif($this->type == "xml"){ $temp = file_get_contents("views/xmlheader.xml") . "\n\r"; }
		
		$this->text .= $temp;
	}
	
	// adds footer to Template
	public function addFooter(){
		if($this->type == "html"){ $this->text .= file_get_contents("views/htmlfooter.htm"); }
		elseif($this->type == "rss"){ $this->text .= file_get_contents("views/rssfooter.xml"); }
		elseif($this->type == "xml"){ $this->text .= file_get_contents("views/xmlfooter.xml"); }
	}
	
	// adds form for posting link (html).  arguments are for prepopulating a form (for bookmarklet/editing older posts/ etc)
	public function addPostForm($uri = "", $title = "", $desc = "", $tags = "", $id = ""){
		$temp = file_get_contents("views/htmlpostform.htm");
		
		// replace template strings
		$temp = str_replace("#URI#", $uri, $temp);
		$temp = str_replace("#TITLE#", $title, $temp);
		$temp = str_replace("#DESC#", $desc, $temp);
		$temp = str_replace("#ID#", $id, $temp);

		$tag_string = "";
		
		if($tags != ""){
			foreach($tags as $tag){
				$tag_string .= $tag . ",";
			}
		}
		
		$tag_string = substr($tag_string, 0, strlen($tag_string) - 1);
		$temp = str_replace("#TAGS#", $tag_string, $temp);
		
		$this->text .= $temp;
	}
	
	// adds Post object to Template
	public function addPost($post){
		global $date_format;
	
		if($this->type == "html"){ $temp = file_get_contents("views/htmlpost.htm"); }
		elseif($this->type == "rss"){ $temp = file_get_contents("views/rsspost.xml") . "\n\r"; }
		elseif($this->type == "xml"){ $temp = file_get_contents("views/xmlpost.xml") . "\n\r"; }
		
		// replace template strings with actual variables
		$temp = str_replace("#URI#", $post->getURI(), $temp);
		$temp = str_replace("#TITLE#", $post->getTitle(), $temp);
		$temp = str_replace("#DESC#", $post->getDesc(), $temp);
		$temp = str_replace("#ID#", $post->getId(), $temp);

		$tag_string = "";
		$tag_list = "";
		
		if($this->type == "html"){
			$temp = str_replace("#DATE#", date($date_format, $post->getTimestamp()), $temp);
		
			foreach($post->getTags() as $tag){
				$tag_string .= "<a href=\"bkmrk.php?get&tags={$tag}\" title=\"posts tagged with {$tag}\">{$tag}</a> ";
				$tag_list .= $tag . ",";
			}
			
			$tag_list = substr($tag_list, 0, strlen($tag_list) - 1);
		}
		
		elseif($this->type == "xml"){
			$temp = str_replace("#DATE#", date("Y-m-d\TH:i:s\Z", $post->getTimestamp()), $temp);
		
			foreach($post->getTags() as $tag){
				$tag_string .= $tag . " ";
			}
			
			$tag_string = trim($tag_string);
		}
		
		$temp = str_replace("#TAGS#", $tag_string, $temp);
		$temp = str_replace("#TAGSLIST#", $tag_list, $temp);
		
		$this->text .= $temp;
	}
	
	// print out the text
	public function output(){
		print($this->text);
	}
	
	
}

// setup a class to maintain the link object
class Post {
	protected $uri, $title, $desc, $tags, $timestamp, $id;
	
	public function __construct($uri, $title = "", $desc = "", $tags = array(), $timestamp = "", $id = ""){
		if($timestamp == ""){ $timestamp = time(); }
	
		$this->uri = $uri;
		$this->title = $title;
		$this->desc = $desc;
		$this->tags = $tags;
		$this->timestamp = $timestamp;
		$this->id = $id;
	}
	
	// returns various variables of the object
	public function getURI(){
		return $this->uri;
	}
	public function getTitle(){
		return $this->title;
	}
	public function getDesc(){
		return $this->desc;
	}
	public function getTags(){
		sort($this->tags);
		return $this->tags;
	}
	public function getTimestamp(){
		return $this->timestamp;
	}
	public function getId(){
		return $this->id;
	}
	
	/**
	* addPost(uri, title, desc, tags array, timestamp, id) - adds post to link database and tags to tag DB. updates if id is given.
	*/
	public function addPost($uri, $title = "", $desc = "", $tags = array(), $timestamp = "", $id = ""){
		global $base;
	
		if($timestamp == ""){ $timestamp = time(); }
	
		if($id != ""){ $query = "UPDATE posts SET uri='$uri', title='$title', desc='$desc', ts=$timestamp WHERE id=$id"; }
		else{ $query = "INSERT INTO posts (uri, title, desc, ts) VALUES ('$uri', '$title', '$desc', $timestamp)"; }
	
	
		$base->exec($query);
		
		if($id != ""){
			// lazy way of doing it.  delete all tags associated with given id then add the new ones.
			$base->exec("DELETE FROM tags WHERE postid=$id");
			
			foreach($tags as $tag){
				$base->exec("INSERT INTO tags (postid, tag) VALUES ($id, '$tag')");
			}
		}
		
		else{
			// get ID of the post then insert tags into DB
			$id = $base->querySingle("SELECT MAX(id) FROM posts");
			foreach($tags as $tag){
				$base->exec("INSERT INTO tags (postid, tag) VALUES ($id, '$tag')");
			}
		}
	}
	
	/**
	 * deletePost($id) - removes post with id $id from the database.
	 */
	 public static function deletePost($id){
		global $base;
		
		$base->exec("DELETE FROM posts WHERE id=$id");
	 }
	
	/**
	 * getPost([id], [num]) - acquires posts from DB and returns them as Post objects.  if not given $id, returns $num or $def_num latest results as an array
	 */
	public static function getPost($id = "", $num = "", $tags = ""){
		global $base;
	
		if($id != ""){
			$postinfo = $base->query("SELECT uri, title, desc, ts FROM posts WHERE id=$id");
			
			while($post = $postinfo->fetchArray(SQLITE3_ASSOC)){
				$uri = $post['uri'];
				$title = $post['title'];
				$desc = $post['desc'];
				$timestamp = $post['ts'];
				
				// create tag array from tags table
				$tags = array();
				$tagsinfo = $base->query("SELECT tag FROM tags where postid=$id");
				while($tag = $tagsinfo->fetchArray(SQLITE3_ASSOC)){
					$tags[] = $tag['tag'];
				}
				
				// return a Post object
				return(new Post($uri, $title, $desc, $tags, $timestamp, $id));
			}
		}
		
		// otherwise, figure out posts then pull them using Post::getPost(id)
		else{
			global $def_num;
			
			if($num == ""){ $num = $def_num; }
			
			$sql = "SELECT id FROM posts ";
			
			// if given tags, limit to posts that have the tags
			if($tags != ""){
				$sql2 = "SELECT DISTINCT postid FROM tags WHERE ";
				for($i = 0; $i < count($tags); $i++){
					if($i == 0){ $sql2 .= "tag='" . $tags[$i] . "' "; }
					else{ $sql2 .= "INTERSECT SELECT DISTINCT postid FROM tags WHERE tag='" . $tags[$i] . "' "; }
				}
				
				// number of results our query will return
				$count_sql2 = "SELECT COUNT(DISTINCT postid) FROM tags WHERE postid IN (" . $sql2 . ")";
				$count = $base->querySingle($count_sql2);
				
				// warn the user if no results are found for the requested tag(s)
				if($count == 0){ die("No results exist for that tag intersection."); }
				
				// get the ids to limit the overall query to
				$post_ids = $base->query($sql2);
				
				$sql .= "WHERE ";
				$i = 1;
				
				while($row = $post_ids->fetchArray(SQLITE3_ASSOC)){
					$sql .= "id = " . $row['postid'] . " ";
					if($i != $count){ $sql .= "OR "; }
					
					$i++;
				}
			}
	
			$sql .= "ORDER BY ts DESC LIMIT $num";
		
			$ids = $base->query($sql);

			// return an array of Post objects
			$posts = array();
			while($row = $ids->fetchArray(SQLITE3_ASSOC)){
				$posts[] = Post::getPost($row['id']);
			}
				
				return $posts;
		}
	}	 
}

/** ?get:	return posts	**/
if(isset($_GET['get'])){
	global $default_num;
	$id = "";
	
	$tags = "";

	if(isset($_GET['num'])){ $num = $_GET['num']; }
	else{ $num = $default_num; }
	
	if(isset($_GET['id'])){ $id = $_GET['id']; }
	
	if(isset($_GET['tags'])){
		if($_GET['tags'] != ""){
			$tag_str = $_GET['tags'];
			$tags = explode(",", $tag_str);
		}
	}
	
	// return an rss feed if applicable
	if(isset($_GET['rss'])){
		$printer = new Template("rss");
	}
	
	// otherwise, return html
	else{
		$printer = new Template();
	}
	
	$printer->addHeader($tags, $num);
	
	// get the posts and add them
	$posts = Post::getPost($id, $num, $tags);
	
	// add posts to the template
	foreach($posts as $post){ $printer->addPost($post); }
	
	$printer->addFooter();
	
	if($printer->getType() == "rss"){ header("Content-type: text/xml"); }
	$printer->output();
}

/** ?post:	add a post		**/
elseif(isset($_GET['post'])){
	// display prompt if user isn't submitting a post
	if($_GET['post'] != "post link"){
		$uri = "";
		$title = "";
		$desc = "";
		$tags = "";
		$id = "";
		
		if(isset($_GET['uri'])){ $uri = $_GET['uri']; }
		if(isset($_GET['title'])){ $title = $_GET['title']; }
		if(isset($_GET['desc'])){ $desc = $_GET['desc']; }
		if(isset($_GET['tags'])){ $tags = explode(",", $_GET['tags']); }
		if(isset($_GET['id'])){ $id = $_GET['id']; }
	
		$printer = new Template();
		$printer->addHeader();
		$printer->addPostForm($uri, $title, $desc, $tags, $id);
		$printer->addFooter();
		
		$printer->output();
	}
	
	// post it!
	else{
		$title = "";
		$desc = "";
		$tags = "";
		$timestamp = "";
		$id = "";
	
		$uri = sqlite_escape_string($_GET['uri']);
		if(isset($_GET['title'])){ $title = sqlite_escape_string($_GET['title']); }
		if(isset($_GET['desc'])){ $desc = sqlite_escape_string($_GET['desc']); }
		if(isset($_GET['id'])){ $id = sqlite_escape_string($_GET['id']); }
		if(isset($_GET['timestamp'])){ $timestamp = sqlite_escape_string($_GET['timestamp']); }
		if(isset($_GET['tags'])){
			// strip spaces
			$tags = str_replace(" ", "", sqlite_escape_string($_GET['tags']));
			// turn it into an array
			$tags = explode(",", $tags);
		}
	
		// insert link into DB
		Post::addPost($uri, $title, $desc, $tags, $timestamp, $id);
		
		// reload the homepage
		header("Location: http://" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF']);
	}
}

elseif(isset($_GET['delete']) && isset($_GET['id'])){
	Post::deletePost(sqlite_escape_string($_GET['id']));
	
	// send user to homepage
	header("Location: http://" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF']);
}

/** ?init:	create tables in database **/
elseif(isset($_GET['init']) && $enable_init){
	global $base; 
	
	$base->exec("CREATE TABLE posts ('id' integer primary key autoincrement, 'uri' text, 'title' text, 'desc' text, 'ts' integer)");
	$base->exec("CREATE TABLE tags ('id' integer primary key autoincrement, 'postid' integer, 'tag' text)");
	
	// send user to homepage
	header("Location: http://" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF']);
}

/** ?import:	import del.icio.us XML file.  can be disabled in settings. **/
elseif(isset($_GET['import']) && $enable_import){
	// open the file to import
	$xml = simplexml_load_file($_GET['import']);
	
	// get each post and add it to the database
	foreach($xml->post as $post){
		$uri = $post['href'];
		$title = $post['description'];
		$desc = $post['extended'];
		$tags = explode(" ", $post['tag']);
		$time = strtotime($post['time']);

		Post::addPost($uri, $title, $desc, $tags, $time);
	}
	
	// reload the homepage
	header("Location: http://" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF']);
}

/** ?export:	exports XML file of bookmarks **/
elseif(isset($_GET['export'])){
	// figure out the number of links in the database
	$num_posts = $base->querySingle("SELECT count(id) FROM posts");
	
	// add all the posts to an array.  might be too memory intensive with a lot of data.
	$posts = Post::getPost("", $num_posts);
	
	// pass this array into XML printing template.
	$printer = new Template("xml");
	$printer->addHeader();
	foreach($posts as $post){ $printer->addPost($post); }
	$printer->addFooter();
	
	header("Content-Type: text/xml");
	$printer->output();
	
}
  
/** default:	display $def_num latest posts	**/
else{
	$printer = new Template();
	$printer->addHeader();
	$printer->addPostForm();
	foreach(Post::getPost() as $post){ $printer->addPost($post); }
	$printer->addFooter();
	
	$printer->output();	
}
  
/** close database connection **/
$base->close();
?>
