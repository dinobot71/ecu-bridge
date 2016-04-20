<?php 

/**
 * 
 * web \ http \ MIME - helper (static) class to work with MIME
 * types (Content-Type in HPTT parlance).
 * 
 * @package littlemdesign.com
 * 
 * @author Little m Design (Michael Garvin)
 * @copyright Copyright (c) 2013-, Littl m Design
 * 
 */

class littlemdesign_web_http_MIME {

  /**
   * 
   * $types is the main map of MIME types that we know about.
   * 
   * @var array
   * 
   */
	
  static public $types = array (
        'ez'        => 'application/andrew-inset',
        'atom'      => 'application/atom+xml',
        'jar'       => 'application/java-archive',
        'hqx'       => 'application/mac-binhex40',
        'cpt'       => 'application/mac-compactpro',
        'mathml'    => 'application/mathml+xml',
        'doc'       => 'application/msword',
        'dat'       => 'application/octet-stream',
        'oda'       => 'application/oda',
        'ogg'       => 'application/ogg',
        'pdf'       => 'application/pdf',
        'ai'        => 'application/postscript',
        'eps'       => 'application/postscript',
        'ps'        => 'application/postscript',
        'rdf'       => 'application/rdf+xml',
        'rss'       => 'application/rss+xml',
        'smi'       => 'application/smil',
        'smil'      => 'application/smil',
        'gram'      => 'application/srgs',
        'grxml'     => 'application/srgs+xml',
        'kml'       => 'application/vnd.google-earth.kml+xml',
        'kmz'       => 'application/vnd.google-earth.kmz',
        'mif'       => 'application/vnd.mif',
        'xul'       => 'application/vnd.mozilla.xul+xml',
        'xls'       => 'application/vnd.ms-excel',
        'xlb'       => 'application/vnd.ms-excel',
        'xlt'       => 'application/vnd.ms-excel',
        'xlam'      => 'application/vnd.ms-excel.addin.macroEnabled.12',
        'xlsb'      => 'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
        'xlsm'      => 'application/vnd.ms-excel.sheet.macroEnabled.12',
        'xltm'      => 'application/vnd.ms-excel.template.macroEnabled.12',
        'docm'      => 'application/vnd.ms-word.document.macroEnabled.12',
        'dotm'      => 'application/vnd.ms-word.template.macroEnabled.12',
        'ppam'      => 'application/vnd.ms-powerpoint.addin.macroEnabled.12',
        'pptm'      => 'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
        'ppsm'      => 'application/vnd.ms-powerpoint.slideshow.macroEnabled.12',
        'potm'      => 'application/vnd.ms-powerpoint.template.macroEnabled.12',
        'ppt'       => 'application/vnd.ms-powerpoint',
        'pps'       => 'application/vnd.ms-powerpoint',
        'odc'       => 'application/vnd.oasis.opendocument.chart',
        'odb'       => 'application/vnd.oasis.opendocument.database',
        'odf'       => 'application/vnd.oasis.opendocument.formula',
        'odg'       => 'application/vnd.oasis.opendocument.graphics',
        'otg'       => 'application/vnd.oasis.opendocument.graphics-template',
        'odi'       => 'application/vnd.oasis.opendocument.image',
        'odp'       => 'application/vnd.oasis.opendocument.presentation',
        'otp'       => 'application/vnd.oasis.opendocument.presentation-template',
        'ods'       => 'application/vnd.oasis.opendocument.spreadsheet',
        'ots'       => 'application/vnd.oasis.opendocument.spreadsheet-template',
        'odt'       => 'application/vnd.oasis.opendocument.text',
        'odm'       => 'application/vnd.oasis.opendocument.text-master',
        'ott'       => 'application/vnd.oasis.opendocument.text-template',
        'oth'       => 'application/vnd.oasis.opendocument.text-web',
        'potx'      => 'application/vnd.openxmlformats-officedocument.presentationml.template',
        'ppsx'      => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
        'pptx'      => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xlsx'      => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xltx'      => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
        'docx'      => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'dotx'      => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
        'vsd'       => 'application/vnd.visio',
        'wbxml'     => 'application/vnd.wap.wbxml',
        'wmlc'      => 'application/vnd.wap.wmlc',
        'wmlsc'     => 'application/vnd.wap.wmlscriptc',
        'vxml'      => 'application/voicexml+xml',
        'bcpio'     => 'application/x-bcpio',
        'vcd'       => 'application/x-cdlink',
        'pgn'       => 'application/x-chess-pgn',
        'cpio'      => 'application/x-cpio',
        'csh'       => 'application/x-csh',
        'dcr'       => 'application/x-director',
        'dir'       => 'application/x-director',
        'dxr'       => 'application/x-director',
        'dvi'       => 'application/x-dvi',
        'spl'       => 'application/x-futuresplash',
        'tgz'       => 'application/x-gtar',
        'gtar'      => 'application/x-gtar',
        'hdf'       => 'application/x-hdf',
        'json'      => 'application/json',
        'form'      => 'application/x-www-form-urlencoded',
        'js'        => 'text/javascript',
        'skp'       => 'application/x-koan',
        'skd'       => 'application/x-koan',
        'skt'       => 'application/x-koan',
        'skm'       => 'application/x-koan',
        'latex'     => 'application/x-latex',
        'nc'        => 'application/x-netcdf',
        'cdf'       => 'application/x-netcdf',
        'sh'        => 'application/x-sh',
        'shar'      => 'application/x-shar',
        'swf'       => 'application/x-shockwave-flash',
        'sit'       => 'application/x-stuffit',
        'sv4cpio'   => 'application/x-sv4cpio',
        'sv4crc'    => 'application/x-sv4crc',
        'tar'       => 'application/x-tar',
        'tcl'       => 'application/x-tcl',
        'tex'       => 'application/x-tex',
        'texinfo'   => 'application/x-texinfo',
        'texi'      => 'application/x-texinfo',
        't'         => 'application/x-troff',
        'tr'        => 'application/x-troff',
        'roff'      => 'application/x-troff',
        'man'       => 'application/x-troff-man',
        'me'        => 'application/x-troff-me',
        'ms'        => 'application/x-troff-ms',
        'ustar'     => 'application/x-ustar',
        'src'       => 'application/x-wais-source',
        'xhtml'     => 'application/xhtml+xml',
        'xht'       => 'application/xhtml+xml',
        'xslt'      => 'application/xslt+xml',
        'xml'       => 'application/xml',
        'xsl'       => 'application/xml',
        'dtd'       => 'application/xml-dtd',
        'zip'       => 'application/zip',
        'au'        => 'audio/basic',
        'snd'       => 'audio/basic',
        'mid'       => 'audio/midi',
        'midi'      => 'audio/midi',
        'kar'       => 'audio/midi',
        'mpga'      => 'audio/mpeg',
        'mp2'       => 'audio/mpeg',
        'mp3'       => 'audio/mpeg',
        'aif'       => 'audio/x-aiff',
        'aiff'      => 'audio/x-aiff',
        'aifc'      => 'audio/x-aiff',
        'm3u'       => 'audio/x-mpegurl',
        'wma'       => 'audio/x-ms-wma',
        'wax'       => 'audio/x-ms-wax',
        'ram'       => 'audio/x-pn-realaudio',
        'ra'        => 'audio/x-pn-realaudio',
        'rm'        => 'application/vnd.rn-realmedia',
        'wav'       => 'audio/x-wav',
        'pdb'       => 'chemical/x-pdb',
        'xyz'       => 'chemical/x-xyz',
        'bmp'       => 'image/bmp',
        'cgm'       => 'image/cgm',
        'gif'       => 'image/gif',
        'ief'       => 'image/ief',
        'jpeg'      => 'image/jpeg',
        'jpg'       => 'image/jpeg',
        'jpe'       => 'image/jpeg',
        'png'       => 'image/png',
        'svg'       => 'image/svg+xml',
        'tiff'      => 'image/tiff',
        'tif'       => 'image/tiff',
        'djvu'      => 'image/vnd.djvu',
        'djv'       => 'image/vnd.djvu',
        'wbmp'      => 'image/vnd.wap.wbmp',
        'ras'       => 'image/x-cmu-raster',
        'ico'       => 'image/x-icon',
        'pnm'       => 'image/x-portable-anymap',
        'pbm'       => 'image/x-portable-bitmap',
        'pgm'       => 'image/x-portable-graymap',
        'ppm'       => 'image/x-portable-pixmap',
        'rgb'       => 'image/x-rgb',
        'xbm'       => 'image/x-xbitmap',
        'psd'       => 'image/x-photoshop',
        'xpm'       => 'image/x-xpixmap',
        'xwd'       => 'image/x-xwindowdump',
        'eml'       => 'message/rfc822',
        'igs'       => 'model/iges',
        'iges'      => 'model/iges',
        'msh'       => 'model/mesh',
        'mesh'      => 'model/mesh',
        'silo'      => 'model/mesh',
        'wrl'       => 'model/vrml',
        'vrml'      => 'model/vrml',
        'ics'       => 'text/calendar',
        'ifb'       => 'text/calendar',
        'css'       => 'text/css',
        'csv'       => 'text/csv',
        'html'      => 'text/html',
        'htm'       => 'text/html',
        'txt'       => 'text/plain',
        'plain'     => 'text/plain',
        'asc'       => 'text/plain',
        'rtx'       => 'text/richtext',
        'rtf'       => 'text/rtf',
        'sgml'      => 'text/sgml',
        'sgm'       => 'text/sgml',
        'tsv'       => 'text/tab-separated-values',
        'wml'       => 'text/vnd.wap.wml',
        'wmls'      => 'text/vnd.wap.wmlscript',
        'etx'       => 'text/x-setext',
        'mpeg'      => 'video/mpeg',
        'mpg'       => 'video/mpeg',
        'mpe'       => 'video/mpeg',
        'qt'        => 'video/quicktime',
        'mov'       => 'video/quicktime',
        'mxu'       => 'video/vnd.mpegurl',
        'm4u'       => 'video/vnd.mpegurl',
        'flv'       => 'video/x-flv',
        'asf'       => 'video/x-ms-asf',
        'asx'       => 'video/x-ms-asf',
        'wmv'       => 'video/x-ms-wmv',
        'wm'        => 'video/x-ms-wm',
        'wmx'       => 'video/x-ms-wmx',
        'avi'       => 'video/x-msvideo',
        'ogv'       => 'video/ogg',
        'movie'     => 'video/x-sgi-movie',
        'ice'       => 'x-conference/x-cooltalk',
        'yaml'      => 'application/x-yaml',
        'php'       => 'application/x-php'
  );
  
  /**
   * 
   * standard constructor is disallowed, usage should
   * only be via static methods (its a factory)
   * 
   */
  
  private function __construct() {}
  
  /**
   * 
   * isSupported() - test to see if this extensino is supported.
   * 
   * @param string $extension return true if the given extension
   * is supported.  Note that some reasonable aliases can be used;
   * you can ask if json, plain or form are supported (for example).
   * 
   */
  
  static public function isSupported($extension) {
  	
  	if(self::extToType($extension) !== false) {
      return true;
  	}
  	
  	return false;
  }
  
  /**
   * 
   * isType() - test to see if $type is a known MIME type.
   * 
   * @param string $type
   * 
   * @return boolean return exactly true if the given type if
   * the given type is a known MIME type.
   * 
   */
  
  static public function isType($type) {
  	   
    if(preg_match('/^([^;]+)/', $type, $matches)) {
  	  $type = $matches[1];
  	}
  	
    if(in_array($type, self::$types)) {
      return true;
    }
    return false;
  }
  
  /**
   * 
   * extToType() - convert a file extension to its 
   * MIME type (if  we can).  If not, return exactly false.
   * 
   * @param unknown_type $extension the extension to convert.
   * 
   * @return mixed return the (string) MIME type if we can
   * otherwise return false.
   * 
   */
  
  static public function extToType($extension) {
  	
  	$extension = trim($extension, '.');
  	$extension = strtolower(trim($extension));
  	
  	if(isset(self::$types[$extension])) {
      return self::$types[$extension];
  	}
  	return false;
  }
  
  /**
   * 
   * typeToExt() convert a known MIME type to its 
   * file extension (if we can).  If not found return exactly 
   * false.
   * 
   * @param string $type the MIME type to convert.
   * 
   * @return mixed return the (string) extension if we can
   * otherwise return false.
   * 
   */
  
  static public function typeToExt($type) {
  	
  	$type = strtolower(trim($type));
  	if(preg_match('/^([^;]+)/', $type, $matches)) {
  	  $type = $matches[1];
  	}
  	
  	return array_search($type, self::$types);
  }
  
  static public function autoType($fileName, $actualFile = true) {

  	$ext = pathinfo($fileName, PATHINFO_EXTENSION);
  	  
    /* 
     * if this is actually a file on the server, try to get the
     * actual extension (it might be softlinked).
     * 
     */
  	
  	if($actualFile !== false) {
  		
  	  if(file_exists(realpath($fileName))) {
  	  	$ext = pathinfo(realpath($fileName), PATHINFO_EXTENSION);
  	  }
  	}
  	
  	/* detect */
  	
  	return self::extToType($ext);
  }
  
}

?>