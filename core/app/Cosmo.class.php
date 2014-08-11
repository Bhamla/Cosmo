<?php

/**
 * Cosmo class provides core functionality of the CMS
 */

/**
   // Error codes
   try {
        $stmt->execute($data);
    } catch (PDOException $e) {
        return $e->getMessage();
    }
 */
class Cosmo {
    
    private $pdo;
    private $prefix;
    private $salt;
    private $thumbnailSizes = array(320, 512, 1024, 2048);
    
    public function __construct(PDO $pdo, $prefix, $salt=null)
    {
        $this->pdo = $pdo;
        $this->prefix = $prefix;
        $this->salt = $salt;
    }
    
    ##################################################
    #                   Blocks                       #
    ##################################################
    
    /**
     * Create a new block
     * @param str name Name of the new block
     * @return mixed Returns last insert ID on success. False on fail.
     */
    public function blockCreate($name)
    {
        $stmt = $this->pdo->prepare('INSERT INTO blocks (name) VALUES (?)');
        $data = array($name);
        if($stmt->execute($data))
            return $this->pdo->lastInsertId();
        else
            return FALSE;
    }
    
    /**
     * Fetch all the blocks
     * @return array Array with names 'id', 'name', 'block', 'priority', and 'area'
     */
    public function blockRead(){
        $stmt = $this->pdo->prepare('SELECT * FROM blocks');
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        while($row = $stmt->fetch())
            $blocks[] = $row;
        
        return $blocks;
    }
    
    /**
     * Delete a block
     * @param int $blockID Block ID
     * @return boolean
     */
    public function blockDelete($blockID)
    {
        $stmt = $this->pdo->prepare('DELETE FROM blocks WHERE id=?');
        $data = array($blockID);
        return $stmt->execute($data);
    }
    
    /**
     * Save a block in HTML
     * @param string $block Block HTML
     * @return boolean
     */
    public function blockUpdate($name, $block, $priority, $area, $blockID)
    {
        $stmt = $this->pdo->prepare('UPDATE blocks SET name=?, block=?, priority=?, area=? WHERE id=?');
        $data = array($name, $block, $priority, $area, $blockID);
        return $stmt->execute($data);
    }
    
    /**
     * Fetch a specific block
     * @param type $this->pdo
     * @param type $blockID
     */
    public function blockFetch($blockID)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM blocks WHERE id=?');
        $data = array($blockID);
        $stmt->execute($data);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $row = $stmt->fetch();
        return $row['block'];
    }
    
    /**
     * Fetch all blocks
     * @param string $pageType Page type. e.g. 'blog'
     * @param string $url Page URL e.g. /blog
     * @return array 2d array with 'block', 'priority', and 'area' names
     */
    public function blockFetchAll($pageType=null, $url=null)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM blocks ORDER BY priority DESC');
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        while($row = $stmt->fetch())
        {
            $blockID = $row['id'];
            $name = $row['name'];
            $block = $row['block'];
            $priority = $row['priority'];
            $area = $row['area'];
            $pagePass = TRUE;
            $typePass = TRUE;
            $skip = FALSE;
            $typeSkip = FALSE;
            
            // Get requirements
            $stmt2 = $this->pdo->prepare('SELECT * FROM blocks_requirements WHERE blocks_id=?');
            $data2 = array($blockID);
            $stmt2->execute($data2);
            $stmt2->setFetchMode(PDO::FETCH_ASSOC);
            if($stmt2->rowCount())
            {
                // Iterate through each requirement
                while($row2 = $stmt2->fetch())
                {
                    $requirement = $row2['requirement'];
                    
                    // Check if requirement was met
                    switch($row2['type'])
                    {
                        // Show only on these pages:
                        case 'visible':
                            // Check for a wildcard at the end of this requirement
                            if(strpos($requirement, '*') === strlen($requirement)-1)
                            {
                                // Check if the URL starts with the requirement
                                if(strpos($url, substr($requirement, 0, strlen($requirement)-1)) === 0 && !$skip)
                                {
                                    $pagePass = TRUE;
                                    $skip = TRUE;
                                } else if(!$skip)
                                    $pagePass = FALSE;
                                
                            } else if($requirement === $url && !$skip) // User is on an allowed page. Skip further checks.
                            {
                                $pagePass = TRUE;
                                $skip = TRUE;
                            } else if(!$skip) // This page doesn't match this requirement.
                                $pagePass = FALSE;

                            break;
                            
                        // Show on all pages except these:
                        case 'invisible':
                            // Check for a wildcard at the end of this requirement
                            if(strpos($requirement, '*') === strlen($requirement)-1)
                            {
                                // Check if the URL starts with the requirement
                                if(strpos($url, substr($requirement, 0, strlen($requirement)-1)) === 0)
                                    $pagePass = FALSE;
                                
                            } else if($requirement === $url)
                                $pagePass = FALSE;

                            break;
                            
                        // Show only for this type
                        case 'type':
                            if($requirement === $pageType && !$typeSkip)
                            {
                                $typePass = TRUE;
                                $typeSkip = TRUE;
                            } else if(!$typeSkip)
                                $typePass = FALSE;

                            break;

                        default:
                            break;
                    }
                }
                
                // If block passes requirements, include in array
                if($pagePass && $typePass)
                    $blockArray[] = array('name'=>$name, 'block'=>$block, 'priority'=>$priority, 'area'=>$area);
                
            } else // No requirements
                $blockArray[] = array('name'=>$name, 'block'=>$block, 'priority'=>$priority, 'area'=>$area);
        }
        
        return $blockArray;
    }
    
    ##################################################
    #              Block Requirements                #
    ##################################################
    
    /**
     * Add a new requirement to a block
     * @param int $blockID Block id
     * @param string $type Type of requirement. 'visible' (for URLs), 'invisible' (for URLs), or 'restrict' (for page types)
     * @param string $requirement URL or page type
     * @return boolean
     */
    public function blockRequirementsCreate($blockID, $type, $requirement){
        if($blockID && $type !== 'type' && $requirement !== '')
        {
            if($type === 'visible' || $type === 'invisible')
            {
                // Make sure URLs start with a slash '/'
                if(strpos($requirement, '/') !== 0)
                    $requirement = '/' + $requirement;
            }
            $stmt = $this->pdo->prepare('INSERT INTO blocks_requirements (blocks_id, type, requirement) VALUES (?,?,?)');
            $data = array($blockID, $type, $requirement);
            return $stmt->execute($data);
        } else
            return FALSE;
    }
    
    /**
     * Fetch all block requirements
     * @param int $blockID Block id
     * @return array Array with block requirement columns 'id', 'blocks_id', 'type', and 'requirement'
     */
    public function blockRequirementsRead($blockID){
        $stmt = $this->pdo->prepare('SELECT * FROM blocks_requirements WHERE blocks_id=?');
        $data = array($blockID);
        $stmt->execute($data);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        while($row = $stmt->fetch())
            $requirements[] = $row;
        
        return $requirements;
    }
    
    /**
     * Update a requirement for a block to be displayed
     * @param int $requirementID Requirement id
     * @param int $blockID Block id
     * @param string $type Type of requirement. 'visible' (for URLs), 'invisible' (for URLs), or 'restrict' (for page types)
     * @param string $requirement URL or page type
     * @return boolean
     */
    public function blockRequirementsUpdate($requirementID, $blockID, $type, $requirement){
        $stmt = $this->pdo->prepare('UPDATE blocks_requirements SET blocks_id=?, type=?, requirement=? WHERE id=?');
        $data = array($blockID, $type, $requirement, $requirementID);
        return $stmt->execute($data);
    }
    
    /**
     * Delete all requirements for a given block
     * @param int $blockID Block id to delete
     * @return boolean
     */
    public function blockRequirementsDelete($blockID){
        $stmt = $this->pdo->prepare('DELETE FROM blocks_requirements WHERE blocks_id=?');
        $data = array($blockID);
        return $stmt->execute($data);
    }
    
    ##################################################
    #                   Content                      #
    ##################################################
    
    /**
     * Create a new page
     * @param str $title
     * @param str $description
     * @param str $header
     * @param str $subheader
     * @param str $body
     * @param str $url
     * @param str $compiledHTML Compiled HTML of the entire page for the snapshot
     * @param str $type Type of page. e.g. 'blog', or 'page'
     * @param str $published 'Y' or 'N'
     * @param str $publishedDate Date the article was published on
     * @return boolean
     */
    public function contentCreate($title, $description, $header, $subheader, $body, $url, $author, $type='page.html', $published='Y', $publishedDate=null){
        // Make sure URL starts with a slash '/'
        if(strpos($url, '/') !== 0)
            $url = '/' . $url;
            
        // Save to database
        $stmt = $this->pdo->prepare('INSERT INTO content (title, description, header, subheader, body, url, type, published, published_date, author, timestamp) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $data = array($title, $description, $header, $subheader, $body, $url, $type, $published, $publishedDate, $author, time());
        if($stmt->execute($data))
            return $this->pdo->lastInsertId();
        else
            return FALSE;
    }
    
    /**
     * Get the page content for the specified URL
     * @param string URL
     * @param boolean TRUE if the user is an administrator and can view unpublished content
     * @return array Returns an array of all available page fields
     */
    public function contentRead($url=NULL, $admin=NULL){
        if($url)
        {
            // Remove the prefix from the URL
            if(!empty($this->prefix)){
                $prefix = substr($this->prefix, 0, strlen($this->prefix)-1); // Remove trailing slash '/'
                $url = str_replace ($prefix, '', $url);
            }
            
            // Lookup page in URL
            $stmt = $this->pdo->prepare("SELECT * FROM content WHERE url=?");
            $data = array($url);
            $stmt->execute($data);
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $row = $stmt->fetch();
            
            // Make sure page exists and is published, or user is an administrator
            if($row && ($row['published'] === 'Y' || $admin))
            {
                // Get extras
                $extras = self::contentExtrasRead($row['id']);
                $tags = self::contentTagsRead($row['id']);
                return array(
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'header' => $row['header'],
                    'subheader' => $row['subheader'],
                    'body' => $row['body'],
                    'url' => $row['url'],
                    'published' => $row['published'],
                    'published_date' => $row['published_date'],
                    'tags' => $tags,
                    'type' => $row['type'],
                    'author' => $row['author'],
                    'timestamp' => $row['timestamp'],
                    'extras' => $extras
                );
            } else if($row['published'] === 'N'){
                return FALSE;
            } else {
                // See if URL changed, if so, redirect the user to the new page
                $stmt = $this->pdo->prepare("SELECT * FROM revisions WHERE url=? LIMIT 1");
                $data = array($url);
                $stmt->execute($data);
                $stmt->setFetchMode(PDO::FETCH_ASSOC);
                if($row = $stmt->fetch())
                {
                    // Grab new URL
                    $stmt = $this->pdo->prepare("SELECT * FROM content WHERE id=?");
                    $data = array($row['content_id']);
                    $stmt->execute($data);
                    $stmt->setFetchMode(PDO::FETCH_ASSOC);
                    if($row = $stmt->fetch()) // Updated the URL
                        return array('redirect' => $row['url']);
                    else // Deleted the page
                        return FALSE;
                } else
                    return FALSE;
            }
        } else // List all pages
        {
            $stmt = $this->pdo->prepare('SELECT id, title, url, type, published, published_date, author FROM content');
            $stmt->execute();
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $i = 0;
            while($row = $stmt->fetch()){
                $results[$i] = $row;
                $results[$i]['tags'] = self::contentTagsRead($row['id']);
                $i++;
            }
            
            return $results;
        }
    }
    
    /**
     * Create a new page
     * @param int $contentID Content id to update
     * @param str $title
     * @param str $description
     * @param str $header
     * @param str $subheader
     * @param str $body
     * @param str $url
     * @param str $compiledHTML Compiled HTML of the entire page for the snapshot
     * @param str $type Type of page. e.g. 'blog', or 'page'
     * @param str $published 'Y' or 'N'
     * @param str $publishedDate Date the article was published on
     * @return boolean
     */
    public function contentUpdate($contentID, $title, $description, $header, $subheader, $body, $url, $author, $type, $published='N', $publishedDate=null){
        // Make sure URL starts with a slash '/'
        if(strpos($url, '/') !== 0)
            $url = '/' . $url;
        // Save to database
        $stmt = $this->pdo->prepare('UPDATE content SET title=?, description=?, header=?, subheader=?, body=?, url=?, type=?, published=?, published_date=?, author=?, timestamp=? WHERE id=?');
        $data = array($title, $description, $header, $subheader, $body, $url, $type, $published, $publishedDate, $author, time(), $contentID);
        return $stmt->execute($data);
    }
    
    /**
     * Delete content
     * @param int $contentID Content ID
     * @return boolean
     */
    public function contentDelete($contentID){
        // Don't delete the 'new' page
        if($contentID != 1){
            $stmt = $this->pdo->prepare('DELETE FROM content WHERE id=?');
            $data = array($contentID);
            return $stmt->execute($data);
        } else
            return FALSE;
    }
    
    /**
     * Save an html snapshot in the snapshots directory
     * @param str $html HTML of the page
     * @param str $url URL of the page
     * @return Number of bytes written
     */
    public function snapshotSave($html, $url){
        // Save html to snapshots directory
        $fileLocation = $_SERVER['DOCUMENT_ROOT']  . "/snapshots/$url.html";
        $file = fopen($fileLocation, "w");
        $returnVar = fwrite($file,$html);
        fclose($file);
        return $returnVar;
    }
    
    ##################################################
    #              Content Extras                    #
    ##################################################
    
    /**
     * Add extra content to a page
     * @param int $contentID Content ID
     * @param string $extra Content of the extra
     * @return boolean
     */
    public function conentExtrasCreate($contentID, $name, $extra)
    {
        $stmt = $this->pdo->prepare('INSERT INTO content_extras (content_id, name, extra) VALUES (?,?,?)');
        $data = array($contentID, $name, $extra);
        return $stmt->execute($data);
    }
    
    /**
     * Get all extra content for the page
     * @param int $contentID Content ID
     * @return array Array with strings of data
     */
    public function contentExtrasRead($contentID){
        $stmt = $this->pdo->prepare('SELECT * FROM content_extras WHERE content_id=?');
        $data = array($contentID);
        $stmt->execute($data);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        while($row = $stmt->fetch())
            $extras[$row['name']] = $row['extra'];
        
        return $extras;
    }
    
    /**
     * Delete all extras for a page
     * @param int $contentID Content id
     * @return boolean
     */
    public function contentExtrasDelete($contentID){
        $stmt = $this->pdo->prepare('DELETE FROM content_extras WHERE content_id=?');
        $data = array($contentID);
        return $stmt->execute($data);
    }
    
    ##################################################
    #               Content Tags                     #
    ##################################################
    
    /**
     * Create a new tag for a page
     * @param int $contentID Content id
     * @param str $tag Tag
     * @return boolean
     */
    public function contentTagsCreate($contentID, $tag){
        $stmt = $this->pdo->prepare('INSERT INTO content_tags (content_id, tag) VALUES (?,?)');
        $data = array($contentID, strtolower(trim($tag)));
        return $stmt->execute($data);
    }
    
    /**
     * Get all tags for a page
     * @param int $contentID Content id
     * @return array Array of tags
     */
    public function contentTagsRead($contentID){
        $stmt = $this->pdo->prepare('SELECT * FROM content_tags WHERE content_id=?');
        $data = array($contentID);
        $stmt->execute($data);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        while($row = $stmt->fetch())
            $tags[] = $row['tag'];
        
        return $tags;
    }
    
    /**
     * Delete all tags for a page
     * @param int $contentID Content id
     * @return boolean
     */
    public function contentTagsDelete($contentID){
        $stmt = $this->pdo->prepare('DELETE FROM content_tags WHERE content_id=?');
        $data = array($contentID);
        return $stmt->execute($data);
    }
    
    ##################################################
    #                   Email                        #
    ##################################################
    
    /**
     * Save a file to the 'uploads' folder. Insert record into database.
     * @return boolean
     */
    public function email($to, $subject, $message)
    {
        return mail($to, $subject, $message);
    }
    
    
    ##################################################
    #                   Files                        #
    ##################################################
    
    /**
     * Save a file to the 'uploads' folder. Insert record into database.
     * @return boolean
     */
    public function fileCreate($file=null)
    {
        $fileExtensions = array(
            'xls',
            'csv',
            'mrd',
            'indd',
            'ai',
            'psd'
        );
        $compressedExtensions = array(
            'zip',
            'zipx',
            'gzip',
            'rar',
            'gz'
        );
        $spreadsheetExtensions = array(
            'xls',
            'xlsx',
            'xlr',
            'numbers',
            'ods',
            'wks'
        );
        $docExtensions = array(
            'doc',
            'docx',
            'odt',
            'pages',
            'rtf',
            'txt',
            'wps'
        );
        $videoExtensions = array(
            'mov',
            'mp4',
            'wmv'
        );
        $audioExtensions = array(
            'mp3',
            'wmv'
        );
        $imageExtensions = array(
            'jpg',
            'png',
            'gif'
        );
        
        if($file)
        {
            $extension = end(explode('.',$file));
            
            if($extension === 'pdf')
                $type = 'pdf';
            else if($extension === 'ppt')
                $type = 'ppt';
            else if(in_array($extension, $spreadsheetExtensions))
                $type = 'spreadsheet';
            else if(in_array($extension, $docExtensions))
                $type = 'doc';
            else if(in_array($extension, $compressedExtensions))
                $type = 'compressed';
            else if(in_array($extension, $fileExtensions))
                $type = 'file';
            else if(strpos($file, 'youtube') || strpos($file, 'youtu.be') || strpos($file, 'vimeo')){
                $type = 'video';
                if(strpos($file, 'http') !== 0)
                    $file = 'http://' . $file;
            } else if(in_array($extension, $videoExtensions))
                $type = 'video';
            else if(in_array($extension, $audioExtensions))
                $type = 'audio';
            else if(in_array($extension, $imageExtensions))
                $type = 'image';
            
            $stmt = $this->pdo->prepare('INSERT INTO files (filename, type, timestamp) VALUES (?,?,?)');
            $data = array($file, $type, time());
            return $stmt->execute($data);
        } else
        {
            $originalName = $_FILES["file"]["name"];
            $nameParts = explode('.', $originalName);
            $extension = end($nameParts);
            $name = uniqid();
            $filename =  $nameParts[0] .'-'. $name . '.' . $extension;
            $tempPath = $_FILES[ 'file' ][ 'tmp_name' ];
            $dir = dirname( __FILE__ );
            $dir = str_replace('/core/app', '', $dir);
            $uploadPath = $dir . '/uploads/' . $filename;
            
            if($extension === 'pdf')
                $type = 'pdf';
            else if($extension === 'ppt')
                $type = 'ppt';
            else if(in_array($extension, $spreadsheetExtensions))
                $type = 'spreadsheet';
            else if(in_array($extension, $docExtensions))
                $type = 'doc';
            else if(in_array($extension, $compressedExtensions))
                $type = 'compressed';
            else if(in_array($extension, $fileExtensions))
                $type = 'file';
            else if(strpos($file, 'youtube') || strpos($file, 'youtu.be') || strpos($file, 'vimeo')){
                $type = 'video';
                if(strpos($file, 'http') !== 0)
                    $file = 'http://' . $file;
            } else if(in_array($extension, $videoExtensions))
                $type = 'video';
            else if(in_array($extension, $audioExtensions))
                $type = 'audio';
            else if(in_array($extension, $imageExtensions))
                $type = 'image';
            
            // Make thumbnails
            $responsive = 'yes';
            foreach($this->thumbnailSizes as $size){
                if(!self::makeThumbnail($tempPath, "$dir/uploads/$name-$size.$extension", $size, 0, 100))
                    $responsive = 'no';
            }
            
            if(move_uploaded_file($tempPath, $uploadPath))
            {   
                // Insert into database
                $stmt = $this->pdo->prepare('INSERT INTO files (filename, responsive, type, timestamp) VALUES (?,?,?,?)');
                if($type === 'video' && (strpos($file, 'youtube') || strpos($file, 'youtu.be') || strpos($file, 'vimeo')))
                    $data = array($filename, $responsive, $type, time());
                else {
                    $filename = '/uploads/' . $filename;
                    $data = array($filename, $responsive, $type, time());
                }
                $stmt->execute($data);
                return array('id'=>$this->pdo->lastInsertId(), 'filename'=>$filename, 'responsive'=>$responsive, 'type'=>$type);
            } else
                return FALSE;
        }
    }
    
    /**
     * List all files that have been uploaded
     * @return array Array of files columns
     */
    public function fileRead(){
        $stmt = $this->pdo->prepare('SELECT * FROM files ORDER BY timestamp DESC');
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        while($row = $stmt->fetch())
        {
            // Get tags
            $files[] = array(
                'id' => $row['id'],
                'filename' => $row['filename'],
                'responsive' => $row['responsive'],
                'tags' => self::fileTagsRead($row['id']),
                'type' => $row['type']
            );
        }
        return $files;
    }
    
    /**
     * List all files that have been uploaded
     * @return array Array of files columns
     */
    public function fileReadRecord($fileID=null, $filename=null){
        if($fileID)
        {
            $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id=?');
            $data = array($fileID);
            $stmt->execute($data);
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $row = $stmt->fetch();
            // Get tags
            $files = array(
                'id' => $row['id'],
                'url' => $row['filename'],
                'responsive' => $row['responsive'],
                'tags' => self::fileTagsRead($row['id']),
                'type' => $row['type']
            );
        } else
        {
            $stmt = $this->pdo->prepare('SELECT * FROM files WHERE filename=?');
            $data = array($filename);
            $stmt->execute($data);
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $row = $stmt->fetch();
            // Get tags
            $files = array(
                'id' => $row['id'],
                'url' => $row['filename'],
                'responsive' => $row['responsive'],
                'tags' => self::fileTagsRead($row['id']),
                'type' => $row['type']
            );
        }
        
        return $files;
    }
    
    /**
     * Update the title and tags of an image
     * @param int $fileID File ID
     * @param string $responsive Is the file responsive or not. "yes" or "no"
     * @return boolean True if title update was executed, false if not
     */
    public function fileUpdate($fileID, $responsive)
    {
        // Update the title
        $stmt = $this->pdo->prepare('UPDATE files SET responsive=? WHERE id=?');
        $data = array($responsive, $fileID);
        return $stmt->execute($data);
    }
    
    /**
     * Delete a file from the uploads folder and the database
     * @param string $fileID File ID
     * @return boolean
     */
    public function fileDelete($fileID)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id=?');
        $data = array($fileID);
        $stmt->execute($data);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $row = $stmt->fetch();
        $filename = $row['filename'];
        
        // Delete file from db
        $stmt = $this->pdo->prepare('DELETE FROM files WHERE id=?');
        $data = array($fileID);
        $stmt->execute($data);

        // Delete tags of associated file
        $stmt = $this->pdo->prepare('DELETE FROM files_tags WHERE files_id=?');
        $data = array($fileID);
        $stmt->execute($data);
        
        $fileParts = explode('.', $filename);
        $name = $fileParts[0];
        $extension = $fileParts[1];
        
        // Remove thumbnails
        if(strpos($filename, '/uploads/') === 0){
            foreach($this->thumbnailSizes as $size)
                unlink($_SERVER['DOCUMENT_ROOT'] . "$name-$size.$extension");
        }
        
        // Delete file from uploads folder
        return unlink($_SERVER['DOCUMENT_ROOT'] . $filename);
    }
    
    /**
     * Original source: https://stackoverflow.com/questions/12661/efficient-jpeg-image-resizing-in-php
     * Resize images for thumbnails/mobile
     * @param str $sourcefile
     * @param str $endfile
     * @param int $thumbwidth
     * @param int $thumbheight
     * @param int $quality
     */
    public function makeThumbnail($sourcefile, $endfile, $thumbwidth, $thumbheight, $quality){
        // Takes the sourcefile (path/to/image.jpg) and makes a thumbnail from it
        // and places it at endfile (path/to/thumb.jpg).
        
        // Load image and get image size.
        $type = exif_imagetype($sourcefile); // [] if you don't have exif you could use getImageSize() 
        switch ($type) { 
            case 1 : 
                $img = imageCreateFromGif($sourcefile); 
            break; 
            case 2 : 
                $img = imageCreateFromJpeg($sourcefile); 
            break; 
            case 3 : 
                $img = imageCreateFromPng($sourcefile); 
            break; 
            case 6 : 
                $img = imageCreateFromBmp($sourcefile); 
            break; 
        }
        
        $width = imagesx($img);
        $height = imagesy($img);
        
        // Don't make images larger than the original
        if($thumbwidth > $width)
            $thumbwidth = $width;
        
        if ($width > $height) {
            $newwidth = $thumbwidth;
            $divisor = $width / $thumbwidth;
            $newheight = floor( $height / $divisor);
        } else {
            $newheight = $thumbheight;
            $divisor = $height / $thumbheight;
            $newwidth = floor( $width / $divisor );
        }
        
        // Create a new temporary image.
        $tmpimg = imagecreatetruecolor( $newwidth, $newheight );
        
        // Copy and resize old image into new image.
        imagecopyresampled( $tmpimg, $img, 0, 0, 0, 0, $newwidth, $newheight, $width, $height );

        // Save thumbnail into a file.
        $returnVal = imagejpeg($tmpimg, $endfile, $quality);

        // release the memory
        imagedestroy($tmpimg);
        imagedestroy($img);
        
        return $returnVal;
    }
    
    ##################################################
    #                 File Tags                      #
    ##################################################
    
    /**
     * Create a new tag
     * @param int $fileID File's id
     * @param str $tag Tag name
     * @return boolean TRUE on success, FALSE on failure to insert record.
     */
    public function fileTagsCreate($fileID, $tag)
    {
        if(!empty($tag))
        {
            $stmt = $this->pdo->prepare('INSERT INTO files_tags (files_id, tag) VALUES (?,?)');
            $data = array($fileID, $tag);
            return $stmt->execute($data);
        } else 
            return FALSE;
    }
    
    /**
     * Get all tags for a given file
     * @param int $fileID File ID
     * @return array Array of all tags
     */
    public function fileTagsRead($fileID=null, $tag=null){
        if(!empty($fileID))
        {
            $stmt = $this->pdo->prepare('SELECT * FROM files_tags WHERE files_id=?');
            $data = array($fileID);
            $stmt->execute($data);
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            while($row = $stmt->fetch())
                $tags[] = $row['tag'];
        } else
        {
            $stmt = $this->pdo->prepare('SELECT DISTINCT tag FROM files_tags WHERE tag LIKE ?');
            $data = array($tag . '%');
            $stmt->execute($data);
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            while($row = $stmt->fetch())
                $tags[] = $row['tag'];
        }
        return $tags;
    }
    
    /**
     * Update a tag
     * @param string $oldTag Old tag
     * @param string $newTag New tag
     * @return boolean
     */
    public function fileTagsUpdate($oldTag, $newTag){
        $stmt = $this->pdo->prepare('UPDATE files_tags SET tag=? WHERE tag=?');
        $data = array($newTag, $oldTag);
        return $stmt->execute($data);
    }
    
    /**
     * Delete a tag
     * @param string $tag Tag
     * @return boolean
     */
    public function fileTagsDelete($fileID, $tag){
        if($fileID)
        {
            $stmt = $this->pdo->prepare('DELETE FROM files_tags WHERE files_id=?');
            $data = array($fileID);
            return $stmt->execute($data);
        } else
        {
            $stmt = $this->pdo->prepare('DELETE FROM files_tags WHERE tag=?');
            $data = array($tag);
            return $stmt->execute($data);
        }
    }
    
    ##################################################
    #                     Menu                       #
    ##################################################
    
    /**
     * Create a new menu
     * @param string $name Name of the new menu
     * @return mixed Returns last insert ID on success. False on fail.
     */
    public function menuCreate($name)
    {
        $stmt = $this->pdo->prepare('INSERT INTO menus (name) VALUES (?)');
        $data = array($name);
        if($stmt->execute($data))
            return $this->pdo->lastInsertId();
        else
            return FALSE;
    }
    
    /**
     * Get all menus
     * @return array Array with 'id', 'name', 'menu', and 'area'
     */
    public function menuRead(){
        $stmt = $this->pdo->prepare('SELECT * FROM menus');
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        while($row = $stmt->fetch())
            $menus[] = $row;
        
        return $menus;
    }
    
    /**
     * Save a menu in HTML
     * @param string $menu Menu HTML
     * @return boolean
     */
    public function menuUpdate($menuID, $name, $menu, $area)
    {
        $stmt = $this->pdo->prepare('UPDATE menus SET name=?, menu=?, area=? WHERE id=?');
        $data = array($name, $menu, $area, $menuID);
        return $stmt->execute($data);
    }
    
    /**
     * Update a menu's name
     * @param int $menuID Menu ID
     * @param str $name Menu's new name
     * @return boolean
     */
    public function menuUpdateName($menuID, $name)
    {
        $stmt = $this->pdo->prepare('UPDATE menus SET name=? WHERE id=?');
        $data = array($name, $menuID);
        return $stmt->execute($data);
    }
    
    /**
     * Delete a menu
     * @param int $menuID Menu ID
     * @return boolean
     */
    public function menuDelete($menuID)
    {
        $stmt = $this->pdo->prepare('DELETE FROM menus WHERE id=?');
        $data = array($menuID);
        return $stmt->execute($data);
    }
    
    ##################################################
    #                     Misc                       #
    ##################################################
    
    /**
     * Create a new misc record
     * @param string $name Name of the new menu
     * @return mixed Returns last insert ID on success. False on fail.
     */
    public function miscCreate($name, $value)
    {
        $stmt = $this->pdo->prepare('INSERT INTO misc (name, value) VALUES (?,?)');
        $data = array($name, $value);
        if($stmt->execute($data))
            return $this->pdo->lastInsertId();
        else
            return FALSE;
    }
    
    /**
     * Get a misc item by it's name
     * @param str Name to search for
     * @return record
     */
    public function miscRead($name){
        $stmt = $this->pdo->prepare('SELECT * FROM misc WHERE name=?');
        $data = array($name);
        $stmt->execute($data);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        return $stmt->fetch();
    }
    
    /**
     * Update a misc record
     * @param str $name Name of the record to modify
     * @param str $value New value of the record
     * @return boolean
     */
    public function miscUpdate($name, $value)
    {
        $stmt = $this->pdo->prepare('UPDATE misc SET value=? WHERE name=?');
        $data = array($value, $name);
        return $stmt->execute($data);
    }
    
    /**
     * Delete a misc record
     * @param str $name Name of the record to delete
     * @return boolean
     */
    public function miscDelete($name)
    {
        $stmt = $this->pdo->prepare('DELETE FROM misc WHERE name=?');
        $data = array($name);
        return $stmt->execute($data);
    }
    
    ##################################################
    #                   Modules                      #
    ##################################################
    
    /**
     * Install a module
     * @param string $module Module name
     * @return boolean
     */
    public function modulesCreate($module)
    {
        // Add module to database
        $stmt = $this->pdo->prepare('INSERT INTO modules (module, status) VALUES (?,?)');
        $data = array($module, 'inactive');
        if($stmt->execute($data))
            return $this->pdo->lastInsertId ();
        else
            return FALSE;
    }
    
    /**
     * Fetch all modules
     * @return array 2d array with all modules
     */
    public function modulesRead()
    {
        // Get all module folders
        $i = 0;
        foreach (glob('../../modules/*') as $module)
        {
            $moduleFolder = str_replace('../../modules/', '', $module);
            $modules[$i] = json_decode(file_get_contents("../../modules/$moduleFolder/cosmo.json"));
            $modules[$i]->folder = $moduleFolder;
            $modules[$i]->status = 'uninstalled';
            $i++;
        }
        
        // Check installed modules
        $stmt = $this->pdo->prepare('SELECT * FROM modules');
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        while($row = $stmt->fetch())
        {
            for($i=0; $i < count($modules); $i++)
            {
                if($modules[$i]->folder === $row['module']){
                    $modules[$i]->id = $row['id'];
                    $modules[$i]->status = $row['status'];
                }
            }
        }
        return $modules;
    }
    
    /**
     * Update the module's status
     * @param str $module Module name
     * @param str $status Status. active or inactive
     * @return boolean
     */
    public function modulesUpdate($moduleID, $status)
    {
        $stmt = $this->pdo->prepare('UPDATE modules SET status=? WHERE id=?');
        $data = array($status, $moduleID);
        return $stmt->execute($data);
    }
    
    /**
     * Activate a module
     * @param string $module Module name
     * @return boolean
     */
    public function modulesDelete($moduleID)
    {
        // Delete a module
        $stmt = $this->pdo->prepare('DELETE FROM modules WHERE id=?');
        $data = array($moduleID);
        return $stmt->execute($data);
    }
    
    ##################################################
    #                  Passwords                     #
    ##################################################
    
    /**
     * Securely encrypt passwords. Uses bcrypt if available, otherwise uses SHA512
     * @param string $password Password to encrypt
     * @param INT $rounds Number of rounds for bcrypt algo. Between 04 and 31. Higher numbers are more secure, but take longer
     * @return string Returns encrypted password
     */
    public function encrypt($password, $rounds=12)
    {
        // Check if blowfish algorithm is installed
        if(CRYPT_BLOWFISH === 1)
        {
            $uniqueSalt = substr(str_replace('+', '.', base64_encode(pack('N4', mt_rand(), mt_rand(), mt_rand(), mt_rand()))), 0, 22);
            
            // Check which version of blowfish we should use depending on the PHP version
            if(version_compare(PHP_VERSION, '5.3.7') >= 0)
                $version = 'y';
            else
                $version = 'a';
            
            $encryptedPassword = crypt($password, '$2' . $version . '$' . $rounds . '$' . $uniqueSalt);
        }
        else # Use SHA512 if blowfish isn't available
            $encryptedPassword = hash('SHA512', $password);
        
        return $encryptedPassword;
    }
    
    /**
     * Use to check password encrypted with the encrypt() function
     * @param string $username User's entered username
     * @param string $password User's entered password
     * @return mixed User's ID if the passwords match, false if they don't
     */
    public function passwordVerify($username, $password)
    {
        // Get the password stored with the given username
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username=?');
        $data = array(strtolower($username));
        $stmt->execute($data);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $row = $stmt->fetch();
        $dbPassword = $row['password'];
        
        // Check if the password was encrypted with blowfish
        if(strpos($password, '$2y$') === 0 || strpos($password, '$2a$') === 0)
        {
            // Check which version of blowfish we should use depending on the PHP version
            if(version_compare(PHP_VERSION, '5.3.7') >= 0)
                $version = 'y';
            else
                $version = 'a';
            
            $encryptedPassword = crypt($password, $dbPassword);
        }
        else # Use SHA512 if blowfish isn't available
            $encryptedPassword = hash('SHA512', $password);
        
        if($dbPassword === $encryptedPassword)
            return $row['id'];
        else
            return FALSE;
    }
    
    /**
     * Reset user's password.
     * @param str $username Username
     * @return mixed Returns secret one-time-use password reset link on success, FALSE on fail.
     */
    public function passwordReset($username)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username=?');
        $data = array(strtolower($username));
        $stmt->execute($data);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $row = $stmt->fetch();
        
        if($stmt->rowCount()){
            // Encrypted string with hashed password, salt, and timestamp in hours. 
            // Password makes it one-time-use, salt makes it un-duplicateable, timestamp makes it temporary
            $token = $this->encrypt($row['password'] . $this->salt . round(time()/3600));
            $url = 'http://'. $_SERVER['HTTP_HOST'] . $this->prefix . '/reset/'. $row['id'] .'/'. $token;
            $body = 'A request to reset your password has been made. To reset your password, click '. $url .'. If this request was not made by you, ignore this email.';
            $this->email($row['email'], 'Reset your Password', $body);
        } else
            return FALSE;
    }
    
    /**
     * Verify that the reset token is valid
     * @param str $userID User's ID
     * @param str $token Token to verify
     * @return boolean Returns TRUE on valid token, FALSE on invalid token.
     */
    public function passwordResetVerify($userID, $token)
    {
        // Check if the token is still valid (created in the last 48 hours)
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id=?');
        $data = array($userID);
        $stmt->execute($data);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $row = $stmt->fetch();
        $currentHour = round(time()/3600);
        for($i=0; $i<=48; $i++)
        {
            if($token === $this->encrypt($row['password'] . $this->salt . ($currentHour - $i)))
                return TRUE;
        }
        return FALSE;
    }
    
    /**
     * Check if the user is an administrator or not
     * @param string Username
     * @param string Auth Token
     * @return boolean TRUE if user is an admin. FALSE if not.
     */
    public function isUserAdmin($username, $auth_token)
    {
        // Get the user's id
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username=?');
        $data = array(strtolower($username));
        $stmt->execute($data);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $row = $stmt->fetch();
        $userID = $row['id'];
        
        // Make sure token is valid
        if($this->tokenValidate($username, $auth_token))
        {
            // Get user's permissions. See if it is the admin role '1'
            if($this->usersRead(null, $username)==='admin')
                return TRUE;
        }
        
        return FALSE;
    }
    
    ##################################################
    #                Revisions                       #
    ##################################################
    
    /**
     * Create a new revision
     * @param int $contentID Content ID this is a revision of
     * @param str $title
     * @param str $description
     * @param str $header
     * @param str $subheader
     * @param str $body
     * @param str $url
     * @param str $compiledHTML Compiled HTML of the entire page for the snapshot
     * @param str $type Type of page. e.g. 'blog', or 'page'
     * @param str $published 'Y' or 'N'
     * @param str $publishedDate Date the article was published on
     * @return mixed Revision id on true, false on fail
     */
    public function revisionsCreate($contentID, $title, $description, $header, $subheader, $body, $url, $type, $published, $publishedDate, $author){
        if($url){
            // Make sure URL starts with a slash '/'
            if(strpos($url, '/') !== 0)
                $url = '/' . $url;
            $stmt = $this->pdo->prepare('INSERT INTO revisions (content_id, title, description, header, subheader, body, url, type, published, published_date, author, timestamp) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $data = array($contentID, $title, $description, $header, $subheader, $body, $url, $type, $published, $publishedDate, $author, time());
            if($stmt->execute($data))
                return $this->pdo->lastInsertId();
            else
                return FALSE;
        }
    }
    
    /**
     * Get all revisions
     * @param str $url URL
     * @return array 2d array with 'title', 'url', 'type', 'pulished', 'timestamp' fields
     */
    public function revisionsRead($contentID)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM revisions WHERE content_id=? ORDER BY timestamp DESC LIMIT 100');
        $data = array($contentID);
        $stmt->execute($data);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        while($row = $stmt->fetch())
            $revisions[] = $row;
        
        return $revisions;
    }
    
    /**
     * Get all revisions
     * @param str $url URL
     * @return array 2d array with 'title', 'url', 'type', 'pulished', 'timestamp' fields
     */
    public function revisionsReadRecord($revisionID)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM revisions WHERE id=?');
        $data = array($revisionID);
        $stmt->execute($data);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        while($row = $stmt->fetch())
            $revisions[] = $row;
        
        return $revisions;
    }
    
    /**
     * Delete revision
     * @param int $revisionID Revision ID
     * @return boolean
     */
    public function revisionsDelete($revisionID){
        $stmt = $this->pdo->prepare('DELETE FROM revisions WHERE id=?');
        $data = array($revisionID);
        return $stmt->execute($data);
    }
    
    /**
     * Delete all revisions for a piece of content
     * @param int $contentID Content ID you are deleting
     * @return boolean
     */
    public function revisionsDeleteAll($contentID){
        $stmt = $this->pdo->prepare('DELETE FROM revisions WHERE content_id=?');
        $data = array($contentID);
        return $stmt->execute($data);
    }
    
    ##################################################
    #             Revisions Extras                   #
    ##################################################
    
    /**
     * Add extra content to a page
     * @param int $revisionID Revision ID
     * @param int $contentID Content ID
     * @param string $extra Content of the extra
     * @return boolean
     */
    public function revisionsExtrasCreate($revisionID, $contentID, $name, $extra)
    {
        $stmt = $this->pdo->prepare('INSERT INTO revisions_extras (revisions_id, content_id, name, extra) VALUES (?,?,?,?)');
        $data = array($revisionID, $contentID, $name, $extra);
        return $stmt->execute($data);
    }
    
    /**
     * Get all extra content for the page
     * @param int $contentID Content ID
     * @return array Array with strings of data
     */
    public function revisionsExtrasRead($revisionID){
        $stmt = $this->pdo->prepare('SELECT * FROM revisions_extras WHERE revisions_id=?');
        $data = array($revisionID);
        $stmt->execute($data);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        while($row = $stmt->fetch())
            $extras[$row['name']] = $row['extra'];
        
        return $extras;
    }
    
    /**
     * Delete all extras for a page
     * @param int $contentID Content id
     * @return boolean
     */
    public function revisionsExtrasDelete($contentID){
        $stmt = $this->pdo->prepare('DELETE FROM revisions_extras WHERE content_id=?');
        $data = array($contentID);
        return $stmt->execute($data);
    }
    
    ##################################################
    #                  Settings                      #
    ##################################################
    
    /**
     * Get the settings
     * @return boolean
     */
    public function settingsRead()
    {
        $stmt = $this->pdo->prepare('SELECT * FROM settings');
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        return $stmt->fetch();
    }
    
    /**
     * Update the settings
     * @param str $siteName Site's name
     * @param str $slogan Site's slogan
     * @param str $logo URL for the logo
     * @param str $favicon URL for the favicon
     * @param str $email Email address
     * @param str $maintenanceURL Maintenance URL
     * @param str $maintenanceMode Maintenece Mode. 'true' or 'false'
     * return boolean
     */
    public function settingsUpdate($siteName, $slogan, $logo, $favicon, $email, $maintenanceURL, $maintenanceMode)
    {
        $stmt = $this->pdo->prepare('UPDATE settings SET site_name=?, slogan=?, logo=?, favicon=?, email=?, maintenance_url=?, maintenance_mode=?');
        $data = array($siteName, $slogan, $logo, $favicon, $email, $maintenanceURL, $maintenanceMode);
        return $stmt->execute($data);
    }
    
    ##################################################
    #                  Sitemaps                      #
    ##################################################
    
    /**
     * Create a new, empty sitemap file at the specified location
     * @param str $fileLocation Sitemap filename. e.g. /sitemap.xml
     * @return boolean
     */
    public function sitemapCreate($fileLocation)
    {
        $xmlFile = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
        $file = fopen($fileLocation, "w");
        fwrite($file,$xmlFile);
        fclose($file);
        return TRUE;
    }
    
    /**
     * Add a URL to the sitemap
     * @param str $fileLocation File name and location. e.g. /sitemap.xml
     * @param str $url URL to add
     * @param str $lastmod Last modified parameter
     * @param str $changefreq Change frequency parameter
     * @param int $priority Priority parameter
     * @return boolean
     */
    public function sitemapAddURL($fileLocation, $url, $lastmod, $changefreq, $priority=.5){
        // Get current sitemap
        $sitemapStr = file_get_contents($fileLocation);
        // Prepare new record
        $newRecord = '<url>';
        $newRecord .= '<loc>' . $url . '</loc>';
        $newRecord .= '<lastmod>' . $lastmod . '</lastmod>';
        $newRecord .= '<changefreq>' . $changefreq . '</changefreq>';
        $newRecord .= '<priority>' . $priority . '</priority>';
        $newRecord .= '</url>';
        $updatedSitemap = str_replace('</urlset>', $newRecord . '</urlset>', $sitemapStr);

        // Save updated Sitemap
        $file = fopen($fileLocation, "w");
        fwrite($file,$updatedSitemap);
        fclose($file);
        return TRUE;
    }
    
    /**
     * Remove a URL from the sitemap
     * @param str $fileLocation Location of the sitemap. e.g. /sitemap.xml
     * @param str $url URL to remove
     * @return boolean
     */
    public function sitemapRemoveURL($fileLocation, $url)
    {
        $doc = new DOMDocument;
        $doc->preserveWhiteSpace = FALSE;
        $doc->load($fileLocation);
        
        $xPath = new DOMXPath($doc);
        $query = sprintf('//url[./loc[contains(., "%s")]]', $url);
        foreach($xPath->query($query) as $node) {
            $node->parentNode->removeChild($node);
        }
        $doc->formatOutput = TRUE;
        $updatedSitemap = $doc->saveXML();

        // Save updated Sitemap
        $file = fopen($fileLocation, "w");
        fwrite($file,$updatedSitemap);
        fclose($file);
        return TRUE;
    }
    
    ##################################################
    #                   Themes                       #
    ##################################################
    
    /**
     * Get all themes in the 'themes' folder
     * @return array Array with all 'name' of the folders
     */
    public function themesRead()
    {
        // Get all theme folders
        foreach(glob('../../themes/*') as $theme)
            $themes[] = array('name' => str_replace('../../themes/', '', $theme));
        
        return $themes;
    }
    
    /**
     * Get all the page types available to the active theme
     * @param string $themeID Name of the theme. e.g. 'default'
     * @return array Array of page 'type'
     */
    public function themesActive($themeID)
    {
        foreach(glob("../../themes/$themeID/*") as $theme)
        {
            // Only return the html files
            if(strpos($theme, '.html'))
                $types[] = array('type' => str_replace("../../themes/$themeID/", '', $theme));
        }
        return $types;
    }
    
    /**
     * Set the new theme
     * @param str $theme Name of the theme
     * @return boolean
     */
    public function themesUpdate($theme){
        $stmt = $this->pdo->prepare('UPDATE settings SET theme=?');
        $data = array($theme);
        return $stmt->execute($data);
    }
    
    ##################################################
    #                User Management                 #
    ##################################################
    
    /**
     * Create a new user
     * @param string $username Username
     * @param string $password Unencrypted password
     * @return mixed Returns id on insert. False if there was an error
     */
    public function userCreate($username, $email, $password)
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (username, email, password) VALUES (?,?,?)');
        $data = array(strtolower($username), $email, $this->encrypt($password));
        return $stmt->execute($data);
    }
    
    /**
     * List all users
     * @return array Array of all users' info
     */
    public function usersRead($keyword=null, $username=null, $userID=null)
    {
        if($userID)
        {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id=?');
            $stmt->execute(array($userID));
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $row = $stmt->fetch();
            $users = array(
                'username'=>$row['username'],
                'photo'=>$row['photo'],
                'facebook'=>$row['facebook'],
                'twitter'=>$row['twitter'],
                'role'=>$row['role'],
                'email'=>$row['email']
            );
        } else if($keyword)
        {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username LIKE ? OR email LIKE ? LIMIT 250");
            $data = array('%' . $keyword . '%', '%' . $keyword . '%');
            $stmt->execute($data);
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            while($row = $stmt->fetch())
                $users[] = array('id'=>$row['id'], 'username'=>$row['username'], 'email'=>$row['email'], 'role'=>$row['role']);
        } else if($username) // Get user's role
        {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username=?');
            $stmt->execute(array($username));
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $row = $stmt->fetch();
            $users = $row['role'];
        } else 
        {
            $stmt = $this->pdo->prepare('SELECT * FROM users LIMIT 250');
            $stmt->execute();
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            while($row = $stmt->fetch())
                $users[] = array(
                    'username'=>$row['username'],
                    'photo'=>$row['photo'],
                    'facebook'=>$row['facebook'],
                    'twitter'=>$row['twitter'],
                    'role'=>$row['role'],
                    'email'=>$row['email']
                );
        }
        
        return $users;
    }
    
    /**
     * Save a token to the database
     * @param string $username Username
     * @return mixed Returns token on success, FALSE on fail
     */
    public function tokenSave($username)
    {
        $token = $this->randomCharGenerator();
        $hashedToken = $this->encrypt($token);
        $stmt = $this->pdo->prepare('INSERT INTO tokens (username, token) VALUES (?,?)');
        $data = array(strtolower($username), $hashedToken);
        if($stmt->execute($data))
            return $hashedToken;
        else
            return FALSE;
    }
    
    /**
     * Delete a specific token, or all tokens
     * @param string $username Username
     * @param string $token Token
     * @return boolean
     */
    public function tokenDelete($username, $token=null){
        if($token) // Delete given token
        {
            $stmt = $this->pdo->prepare('DELETE FROM tokens WHERE username=? AND token=?');
            $data = array($username, $token);
            return $stmt->execute($data);
        } else // Delete all tokens for this user
        {
            $stmt = $this->pdo->prepare('DELETE FROM tokens WHERE username=?');
            $data = array($username);
            return TRUE; // $stmt->execute($data); // Seems to get called on valid tokens
        }
    }
    
    /**
     * Generate a random 128 character string
     * @param int $chars Number of characters in string. Default is 128
     * @return string String with random characters
     */
    public function randomCharGenerator($chars=128){
        $random_string = "";
        for ($i = 0; $i < $chars; $i++)
        {
            $random_char = chr(round( mt_rand(33, 125)));
            if($random_char !== ';')
                $random_string .= $random_char;
            else
                $i--;
        }

        return $random_string;
    }
    
    /**
     * Check if token and username combination are valid
     * @param string $username username
     * @param string $token Token
     * @return mixed Returns new token on success, false on fail.
     */
    public function tokenValidate($username, $token){
        $stmt = $this->pdo->prepare('SELECT * FROM tokens WHERE username=? AND token=?');
        $data = array($username, $token);
        $stmt->execute($data);
        if($stmt->rowCount())
            return TRUE;
        else
        {
            // Invalid token was used. Token could have been compromised, delete all tokens for security.
            $this->tokenDelete($username);
            return FALSE;
        }
    }
    
    /**
     * Login a user
     * @param string $username Username
     * @param srting $password Password
     * @return mixed Returns the token on success, FALSE on fail.
     */
    public function userLogin($username, $password)
    {
        $userID = $this->passwordVerify($username, $password);
        if($userID)
        {
            return array(
                'id' => $userID,
                'username' => strtolower($username),
                'token' => $this->tokenSave($username),
                'role' => $this->usersRead(null, $username)
            );
        } else
            return FALSE;
    }
    
    /**
     * Change a user's username, email, role, or password
     * @param INT $userID User's ID to be updated
     * @param string $username New username
     * @param string $role User's role
     * @param string $email New email
     * @param string $password New password
     * @return boolean Returns true on successful update, false on error
     */
    public function userUpdate($userID, $username=NULL, $photo=NULL, $facebook=NULL, $twitter=NULL, $role=NULL, $email=NULL, $password=NULL)
    {
        if(!empty($username) && !empty($role) && !empty($email)){
            $stmt = $this->pdo->prepare('UPDATE users SET username=?, photo=?, facebook=?, twitter=?, role=?, email=? WHERE id=?');
            $data = array($username, $photo, $facebook, $twitter, $role, $email, $userID);
            return $stmt->execute($data);
        }else if(!empty($username))
        {
            $stmt = $this->pdo->prepare('UPDATE users SET username=? WHERE id=?');
            $data = array($username, $userID);
            return $stmt->execute($data);
        }else if(!empty($email))
        {
            $stmt = $this->pdo->prepare('UPDATE users SET email=? WHERE id=?');
            $data = array($email, $userID);
            return $stmt->execute($data);
        } else if(!empty($password))
        {
            $stmt = $this->pdo->prepare('UPDATE users SET password=? WHERE id=?');
            $data = array($this->encrypt($password), $userID);
            return $stmt->execute($data);
        }
    }
    
    /**
     * Delete a user
     * @param INT $userID User's ID to delete
     * @return boolean Returns true on successful delete, false on error
     */
    public function userDelete($userID)
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id=?');
        $data = array($userID);
        return $stmt->execute($data);
    }
}

?>