<?php

//
// Image Type code.
//
class ITCode {
  const IT_PNG = 1;
  const IT_GIF = 2;
  const IT_JPEG = 3;
  const IT_SVG = 4;
  const IT_SVGZ = 5;
  const IT_TEXT = 6;
  const IT_HTML = 7;
  const IT_XML = 8;
  const IT_KML = 9;
  const IT_JSON = 10;

  const CONTENT_TYPES = array(
    ITCode::IT_PNG => 'image/png',
    ITCode::IT_GIF => 'image/gif',
    ITCode::IT_JPEG => 'image/jpeg',
    ITCode::IT_SVG => 'image/svg+xml',
    ITCode::IT_SVGZ => 'image/svg+xml',
    ITCode::IT_TEXT => 'text/plain',
    ITCode::IT_HTML => 'text/html',
    ITCode::IT_XML => 'text/xml',
    ITCode::IT_KML => 'application/vnd.google-earth.kml+xml',
    ITCode::IT_JSON => 'application/json'
  );

  const EXTENSIONS = array(
    ITCode::IT_PNG => 'png',
    ITCode::IT_GIF => 'gif',
    ITCode::IT_JPEG => 'jpg',
    ITCode::IT_SVG => 'svg',
    ITCode::IT_SVGZ => 'svgz',
    ITCode::IT_TEXT => 'txt',
    ITCode::IT_HTML => 'html',
    ITCode::IT_XML => 'xml',
    ITCode::IT_KML => 'kml',
    ITCode::IT_JSON => 'json'
  );

  const WMS_FORMATS = array(
    ITCode::IT_PNG => 'image/png',
    ITCode::IT_GIF => 'image/gif',
    ITCode::IT_JPEG => 'image/jpeg',
    ITCode::IT_SVG => 'image/svg+xml',
    ITCode::IT_SVGZ => 'image/svg+xml',
    ITCode::IT_TEXT => 'text/plain',
    ITCode::IT_HTML => 'text/html',
    ITCode::IT_XML => 'text/xml',
    ITCode::IT_KML => 'application/vnd.google-earth.kml+xml',
    ITCode::IT_JSON => 'application/json'
  );

  // Converts extension text to ITC (Image Type Code)
  static public function ext2itc($ext) {
    foreach( self::EXTENSIONS as $itc => $extcand ) {
      if( $ext == $extcand ) {
        return $itc;
      }
    }
    return FALSE;
  }

  // Converts Content-Type text (i.e. "image/png") to ITC (Image Type Code)
  static public function ct2itc($ct) {
    foreach( self::CONTENT_TYPES as $itc => $ctcand ) {
      if( $ct == $ctcand ) {
        return $itc;
      }
    }
    return FALSE;
  }

}

//
// Bin
//
class Bin {
  const XDR = 0; // External Data Rpresentation -> Big Endian
  const NDR = 1; // Network Data Rpresentation -> Little Endian

  private static $endian = FALSE;

  public static function getEndian() {
    if( self::$endian === FALSE ) {
      $data = pack('S', 0x0102);
      $sep = unpack('Ca/Cb',$data);
      if( $sep['a'] == 2 && $sep['b'] == 1 ) {
        // little
        self::$endian = self::NDR;
      }
      else {
        // big
        self::$endian = self::XDR;
      }
    }
    return self::$endian;
  }

  public static function needsReverse($e) {
    if( $e !== FALSE ) {
      if( $e != self::getEndian() ) {
        return TRUE;
      }
    }
    return FALSE;
  }

  public static function packDouble($v, $e = FALSE) {
    $ret = pack('d', (double)$v);
    return !self::needsReverse($e) ? $ret : strrev($e);
  }

  public static function packDoubleArray($a, $e = FALSE) {
    $rev = self::needsReverse($e);
    $ret = '';
    foreach( $a as $v ) {
      $r1 = pack('d', (double)$v);
      if( $rev ) {
        $r1 = strrev($r1);
      }
      $ret = $ret . $r1;
    }
    return $ret;
  }

  public static function packInt32($v, $e = FALSE) {
    $ret = pack('l', (int)$v);
    return !self::needsReverse($e) ? $ret : strrev($e);
  }

}

class WMSQueryString {
  static public function generate($z, $x, $y, $itc, $pmx, $pmy, $prf, $wmslayers, $bg = FALSE) {
    $uext = $prf->t2ub($z, $x, $y, $pmx, $pmy, true);
    $pw = $prf->pts->x + $pmx + $pmx;
    $ph = $prf->pts->y + $pmy + $pmy;
    $fmt = ITCode::WMS_FORMATS[$itc];
    // Calculates LAYERS value and STYLES value.
    $arr_layers = array();
    $arr_styles = array();
    foreach( $wmslayers as $layer ) {
      $layername = '';
      $style = '';
      if( $layer ) {
        if( $layer->layer ) {
          $layername = $layer->layer;
        }
        if( $layer->style ) {
          $style = $layer->style;
        }
      }
      array_push($arr_layers, $layername);
      array_push($arr_styles, $style);
    }
    // BGCOLOR
    if( $bg !== FALSE ) {
      $bgcolor = '&BGCOLOR='.$bg;
    }
    else {
      $bgcolor = '';
    }
    // TRANSPARENT
    if( $itc == ITCode::IT_JPEG ) {
      $transparent = '&TRANSPARENT=FALSE';
    }
    else {
      $transparent = '&TRANSPARENT=TRUE';
    }
    $querystring =
      'VERSION=1.3.0'.
      '&SERVICE=WMS&REQUEST=GetMap'.
      '&LAYERS='.implode(',',$arr_layers).
      '&CRS='.$prf->crs.
      '&STYLES='.implode(',',$arr_styles).
      '&BBOX='.$uext->join(',').
      '&WIDTH='.$pw.
      '&HEIGHT='.$ph.
      '&FORMAT='.$fmt.
      $bgcolor.
      $transparent.
      '&DPI=96'.
      '&MAP_RESOLUTION=96'.
      '&FORMAT_OPTIONS=dpi:96'.
      '&EXCEPTIONS=XML';
    return $querystring;
  }
}

class WmsLayer {
  public $layer;
  public $style;
  public function __construct($layer, $style = '') {
    $this->layer = $layer;
    $this->style = $style;
  }
}

//
// XY point
//
class XY {
  public $x;
  public $y;
  public function __construct($x, $y) {
    $this->x = (double)$x;
    $this->y = (double)$y;
  }

  //
  // Returns text expression.
  //
  public function join($delim) {
    return $this->x.$delim.$this->y;
  }
}

// XY box
class Box {

  static public function fromXYArray($ary) {
    $p = array_shift($ary);
    $xmin = $xmax = $p->x;
    $ymin = $ymax = $p->y;
    foreach( $ary as $p ) {
      $x = $p->x;
      $y = $p->y;
      if( $x < $xmin ) {
        $xmin = $x;
      }
      if( $y < $ymin ) {
        $ymin = $y;
      }
      if( $x > $xmax ) {
        $xmax = $x;
      }
      if( $y > $ymax ) {
        $ymax = $y;
      }
    }
    return new Box($xmin,$ymin,$xmax,$ymax);
  }

  public static function intersects($b1, $b2) {
    return (
      $b1->xmin <= $b2->xmax && $b1->xmax >= $b2->xmin
      && $b1->ymin <= $b2->ymax && $b1->ymax >= $b2->ymin
    );
  }

  public static function intersection($b1, $b2) {
    $xmin = $b1->xmin;
    $ymin = $b1->ymin;
    $xmax = $b1->xmax;
    $ymax = $b1->ymax;
    if( $b2->xmin > $xmin ) {
      $xmin = $b2->xmin;
    }
    if( $b2->ymin > $ymin ) {
      $ymin = $b2->ymin;
    }
    if( $b2->xmax < $xmax ) {
      $xmax = $b2->xmax;
    }
    if( $b2->ymax < $ymax ) {
      $ymax = $b2->ymax;
    }
    return new Box($xmin, $ymin, $xmax, $ymax);
  }

  public $xmin;
  public $ymin;
  public $xmax;
  public $ymax;

  // xmin, ymin, xmax, ymax
  //autoswap: If true, will swap min and max if needed.
  public function __construct($xmin, $ymin, $xmax, $ymax, $autoswap = FALSE) {
    $this->xmin = (double)$xmin;
    $this->ymin = (double)$ymin;
    $this->xmax = (double)$xmax;
    $this->ymax = (double)$ymax;
    if( $autoswap && $this->xmin > $this->xmax ) {
      // flip x
      $x = $this->xmin;
      $this->xmin = $this->xmax;
      $this->xmax = $x;
    }
    if( $autoswap && $this->ymin > $this->ymax ) {
      // flip y
      $y = $this->ymin;
      $this->ymin = $this->ymax;
      $this->ymax = $y;
    }
  }

  //
  // Returns XY array (not polygon (closed))
  //
  public function toXYArray() {
    return array(
      new XY($this->xmin, $this->ymin),
      new XY($this->xmax, $this->ymin),
      new XY($this->xmax, $this->ymax),
      new XY($this->xmin, $this->ymax)
    );
  }

  //
  // Returns [xmin,ymin,xmax,ymax]
  //
  public function toArray() {
    return array($this->xmin,$this->ymin,$this->xmax,$this->ymax);
  }

  //
  // Returns text expression.
  //
  public function join($delim) {
    return $this->xmin.$delim.$this->ymin.$delim.$this->xmax.$delim.$this->ymax;
  }

  //
  // Retursn centeroid.
  //
  public function centroid() {
    return new XY(
      0.5*($this->xmin+$this->xmax),
      0.5*($this->ymin+$this->ymax)
    );
  }

  //
  // Returns EWKB expression.
  // srid: SRID integer
  public function ewkb($srid) {
    $s_srid = ''.$srid;
    if( !preg_match('/^[0-9]+$/', $s_srid) ) {
      if( preg_match('/^EPSG:[0-9]+$/', $s_srid) ) {
        $srid = (int)substr($s_srid,5);
      }
      else {
        $srid = 0;
      }
    }
    // endian
    $ret = pack('C', 0xff & Bin::getEndian());
    // geometry type (polygon)
    if( $srid ) {
      // polygon with srid
      $ret = $ret . Bin::packInt32(0x20000003).Bin::packInt32($srid);
    }
    else {
      // polygon without srid
      $ret = $ret . Bin::packInt32(3);
    }
    // rings (1), points(5)
    $ret = $ret.Bin::packInt32(1).Bin::packInt32(5);
    // points
    $ret = $ret.
      Bin::packDoubleArray(array(
        $this->xmin,$this->ymin,
        $this->xmin,$this->ymax,
        $this->xmax,$this->ymax,
        $this->xmax,$this->ymin,
        $this->xmin,$this->ymin
      ));
    return $ret;
  }
}


//
// Z (zoom) extent
//
class ZExtent {
  public $zmin;
  public $zmax;
  public function __construct($zmin, $zmax) {
    $this->zmin = (int)$zmin;
    $this->zmax = (int)$zmax;
  }
  //
  // Returns wheter $z within the extent.
  //
  public function within($z) {
    return ($z >= $this->zmin && $z <= $this->zmax);
  }
}

// ----------------------------------------------------------------
//
// Profile
//
abstract class ProfileBase {
  public $name; // name
  public $crs; // coordinate reference system
  public $yx; // True if registored order at EPSG is Y-X.
  public $upo; // Unit Point corresponding Pixel (0,0) Point.
  public $upd; // Unit Point corresponding Pixel (w,h) Point.
  public $uext; // Unit extent of world
  public $pts; // tile width
  public $upp0; // unit-per-pixel on Z=0
  public $mpu; // meters per unit, required by getOGCScaleDenominator.
  public $crslong; // long expression of coordinate reference system
  public $scaleset; // WMTS WellKnownScaleSet

  // Converts LonLat Point to Unit Point.
  abstract public function prj($p);
  // Converts Unit Point to LonLat Point.
  abstract public function inv($p);

  public function getName() {
    return $this->name;
  }

  public function getTileSize() {
    return $this->pts;
  }

  // crs: CRS short text. "EPSG:4326", "EPSG:3857",...
  // yx: True if registered axis order is Y-X.
  // upo: Point (unit) corresponding origin (pixel) which must be (0,0).
  // upd: Point (unit) corresponding diagonal point (pixel).
  // pts: Tile size (pixel).
  // tws0: World size (tile) on z=0.
  // mpu: Meters per Unit.
  public function __construct($name, $crs, $yx, $upo, $upd, $pts, $tws0,
      $mpu = 1.0, $crslong = '', $scaleset = '') {
    $this->name = $name;
    $this->crs = $crs;
    $this->yx = $yx;
    $this->upo = $upo;
    $this->upd = $upd;
    $this->uext = Box::fromXYArray(array($upo, $upd));
    $this->pts = $pts;
    $this->upp0 = new XY(
      ($upd->x - $upo->x)/($pts->x*$tws0->x),
      ($upd->y - $upo->y)/($pts->y*$tws0->y)
    );
    $this->mpu = $mpu;
    $this->crslong = $crslong;
    $this->scaleset = $scaleset;
  }

  // Converts Unit Box to Tile Box
  public function ub2tb($z, $box, $pmx, $pmy) {
    $uppx = ($this->upp0->x) / (double)(1 << $z);
    $uppy = ($this->upp0->y) / (double)(1 << $z);
    return new BOX(
      (int)floor((($box->xmin - $this->upo->x)/$uppx + $pmx)/ $this->pts->x),
      (int)floor((($box->ymin - $this->upo->y)/$uppy + $pmy)/ $this->pts->y),
      (int)ceil((($box->xmax - $this->upo->x)/$uppx + $pmx)/ $this->pts->x - 1),
      (int)ceil((($box->ymax - $this->upo->y)/$uppy + $pmy)/ $this->pts->y - 1),
      TRUE
    );
  }

  // Converts a tile to Unit Box.
  // strict If true, flip x and y if $this->yx is true.
  public function t2ub($z, $x, $y, $pmx, $pmy, $strict = FALSE) {
    $uppx = ($this->upp0->x) / (double)(1 << $z);
    $uppy = ($this->upp0->y) / (double)(1 << $z);
    $ux1 = ($x * $this->pts->x - $pmx) * $uppx + $this->upo->x;
    $uy1 = ($y * $this->pts->y - $pmy) * $uppy + $this->upo->y;
    $ux2 = (($x + 1) * $this->pts->x + $pmx) * $uppx + $this->upo->x;
    $uy2 = (($y + 1) * $this->pts->y + $pmy) * $uppy + $this->upo->y;
    // Flip XY if needed.
    if( $strict && $this->yx ) {
      return new Box($uy1, $ux1, $uy2, $ux2, TRUE);
    }
    return new Box($ux1, $uy1, $ux2, $uy2, TRUE);
  }


  // Converts a tile to LonLat Box.
  public function t2llb($z, $x, $y, $pmx, $pmy, $strict = FALSE) {
    return $this->invbox($this->t2ub($z, $x, $y, $pmx, $pmy, $strict));
  }

  // Converts LonLat Box to Tile Box.
  public function llb2tb($z, $llbox, $pmx, $pmy) {
    return $this->ub2tb($z, $this->prjbox($llbox), $pmx, $pmy);
  }

  public function getTileExtent($z, $pmx, $pmy) {
    return $this->ub2tb($z, $this->uext, $pmx, $pmy);
  }

  public function getUnitLeftTopCorner() {
    return $this->upo;
  }

  public function getUnitRightBottomCorner() {
    return $this->upd;
  }

  public function getCrs() {
    return $this->crs;
  }

  // Gets ub forced within the world
  public function intersection($ub) {
    return Box::intersection($ub, $this->uext);
  }

  // Converts LonLat Box to Unit Box.
  public function prjbox($box) {
    $ap1 = $box->toXYArray();
    $ap2 = array();
    foreach( $ap1 as $p1 ) {
      array_push($ap2, $this->prj($p1));
    }
    return Box::fromXYArray($ap2);
  }

  // Converts Unit Box to LonLat Box.
  public function invbox($box) {
    $ap1 = $box->toXYArray();
    $ap2 = array();
    foreach( $ap1 as $p1 ) {
      array_push($ap2, $this->inv($p1));
    }
    return Box::fromXYArray($ap2);
  }

  public function getCRSLong() {
    if( $this->crslong == '' ) {
      return $this->crs;
    }
    return $this->crslong;
  }

  public function getWellKnownScaleSet() {
    return $this->scaleset;
  }

  // OGC 07-057r7:
  // pixelSpan = scaleDenominator × 0.28 10-3 / metersPerUnit(crs);
  public function getOGCScaleDenominator($z) {
    $uppx = ($this->upp0->x) / (double)(1 << $z);
    return $uppx/0.00028 * $this->mpu;
  }
}

//
// Web Mercator Profile
//
class MercProfile extends ProfileBase {
  const R = 6378137.0;

  public function __construct($pts = FALSE) {
    parent::__construct(
      'merc',
      'EPSG:3857',
      false,
      new XY(-20037508.0, 20037508.0),
      new XY(20037508.0, -20037508.0),
      $pts ? $pts : new XY(256,256),
      new XY(1, 1),
      1.0,
      'urn:ogc:def:crs:EPSG:6.18:3:3857',
      'urn:ogc:def:wkss:OGC:1.0:GoogleMapsCompatible'
    );
  }

  public function prj($p) {
    return new XY(
      self::R * (double)$p->x*M_PI/180.0,
      self::R * log(tan(0.25*M_PI + 0.5*(double)$p->y*M_PI/180.0))
    );
  }

  public function inv($p) {
    return new XY (
      (double)$p->x / self::R / M_PI * 180.0,
      asin(tanh($p->y/self::R)) / M_PI * 180.0
    );
  }
}


//
// Geodetic Profile
//
class GeodProfile extends ProfileBase {
  public function __construct($pts = FALSE) {
    parent::__construct(
      'geod',
      'EPSG:4326',
      true,
      new XY(-180.0, 90.0),
      new XY(180.0, -90.0),
      $pts ? $pts : new XY(256,256),
      new XY(2, 1),
      2.0*pi()*6378137.0/360.0,
      'urn:ogc:def:crs:OGC:1.3:CRS84',
      'urn:ogc:def:wkss:OGC:1.0:GoogleCRS84Quad'
    );
  }

  public function prj($p) {
    return new XY($p->x, $p->y);
  }

  public function inv($p) {
    return new XY($p->x, $p->y);
  }
}

// ----------------------------------------------------------------
class RasterCropper {
  public function crop($rowdata, $pmx, $pmy, $pw, $ph, $itc, $bg) {
    $img = imagecreatefromstring($rowdata);
    $imgc = imagecrop($img, array('x'=>$pmx, 'y'=>$pmy, 'width'=>$pw, 'height'=>$ph));
    imagedestroy($img);
    $tmppath = tempnam( sys_get_temp_dir(), '_TILE_CROP_');
    $r = FALSE;
    switch( $itc ) {
    case ITCode::IT_GIF:
      $r = imagegif($imgc, $tmppath);
      break;
    case ITCode::IT_JPEG:
      $r = imagejpeg($imgc, $tmppath);
      break;
    case ITCode::IT_PNG:
      $r = imagepng($imgc, $tmppath);
      break;
    default:
      $r = FALSE;
    }
    // destroy imgc
    imagedestroy($imgc);
    // Reads data
    $data = FALSE;
    if( $r !== FALSE ) {
      $fp = fopen($tmppath, 'r');
      $data = stream_get_contents($fp);
      fclose($fp);
    }
    unlink($tmppath);
    return $data;
  }
  
}

// ----------------------------------------------------------------
// Source
//
abstract class SourceBase {
  public function get($z, $x, $y, $itc, $pmx, $pmy, $prf, $opts, $bg = FALSE) {
    // Gets cropper
    $cropper = $this->getCropper();
    // Without cropper
    if( !$cropper ) {
      return $this->getRaw($z, $x, $y, $itc, 0, 0, $prf, $opts, $bg);
    }
    // With cropper
    $rawdata = $this->getRaw($z, $x, $y, $itc, $pmx, $pmy, $prf, $opts, $bg);
    if( $rawdata === FALSE ) {
      return FALSE;
    }
    // Returns rawdata
    if( $pmx == 0 && $pmy == 0 ) {
      return $rawdata;
    }
    return $cropper->crop($rawdata, $pmx, $pmy, $prf->pts->x, $prf->pts->y, $itc, $bg);
  }

  public function getCropper() {
    return FALSE;
  }
  abstract public function getRaw($z, $x, $y, $itc, $pmx, $pmy, $prf, $opts, $bg = FALSE);
}


// --------------------------------------------------------
// FileSource - Source for filesystem
// --------------------------------------------------------
// --------------------------------
// FileSourceBase
// --------------------------------
abstract class FileSourceBase extends SourceBase {
  private $basedir;
  private $layername;
  private $revy;
  abstract public function dirpath($layername,$z,$x,$y,$itc);
  abstract public function filename($layername,$z,$x,$y,$itc);

  public function getRaw($z, $x, $y, $itc, $pmx, $pmy, $prf, $opts, $bg = FALSE) {
    if( $this->revy ) {
      $y = (1 << $z) - $y - 1;
    }
    $filepath =
      $this->basedir.DIRECTORY_SEPARATOR.
      $this->dirpath($this->layername,$z,$x,$y,$itc).DIRECTORY_SEPARATOR.
      $this->filename($this->layername,$z,$x,$y,$itc);
    if( file_exists($filepath) ) {
      return file_get_contents($filepath);
    }
    return FALSE;
  }

  public function __construct($basedir, $layername, $revy) {
    $this->basedir = $basedir;
    $this->layername = $layername;
    $this->revy = $revy;
  }

}

// --------------------------------
// TMSSource
// --------------------------------
class TMSSource extends FileSourceBase {
  public function dirpath($layername,$z,$x,$y,$itc) {
    return
      $layername.DIRECTORY_SEPARATOR.
      $z.DIRECTORY_SEPARATOR.
      $x;
  }

  public function filename($layername,$z,$x,$y,$itc) {
    return $y.'.'.ITCode::EXTENSIONS[$itc];
  }
}

// --------------------------------------------------------
// MBSource - Source for MBTiles
// --------------------------------------------------------
// --------------------------------
// Setting
// --------------------------------
class MBSourceSetting {
  public $path;
  public $revy; // if true, $y upside down
  public function __construct($path, $revy) {
    $this->path = $path;
    $this->revy = $revy;
  }
}

// --------------------------------
// MBSource
// --------------------------------
class MBSource extends SourceBase {
  private $settings;

  public function getRaw($z, $x, $y, $itc, $pmx, $pmy, $prf, $opts, $bg = FALSE) {
    if( !isset($this->settings[$itc]) ) {
      // mbtiles not found
      return FALSE;
    }
    $setting = $this->settings[$itc];
    if( $setting->revy === TRUE ) {
      $y = (1 << $z) - 1 - $y;
    }
    $sqlite3 = new SQLite3($setting->path, SQLITE3_OPEN_READONLY);
    if( $sqlite3 === FALSE ) {
      return FALSE;
    }
    $data = $sqlite3->querySingle("SELECT tile_data FROM tiles WHERE zoom_level=$z AND tile_column=$x AND tile_row=$y");
    $sqlite3->close();
    // If error, returns FALSE (to send 403 status to the client).
    if( $data == NULL ) {
      return FALSE;
    }
    return $data;
  }
  // dbpaths: hash [(ITC)=>(MBSourceSetting),...]
  public function __construct($settings) {
    $this->settings = $settings;
  }
}

// --------------------------------------------------------
// WMSSource - Source for WMS (via http)
// --------------------------------------------------------
class WMSSource extends SourceBase {
  private $baseurl;
  private $delim;

  public function __construct($baseurl) {
    $this->baseurl = $baseurl;
      $this->delim = '?';
    if( strpos($baseurl,'?') !== FALSE ) {
      $this->delim = '&';
    }
  }

  public function getRaw($z, $x, $y, $itc, $pmx, $pmy, $prf, $opts, $bg = FALSE) {
    $qs = WMSQueryString::generate($z, $x, $y, $itc, $pmx, $pmy, $prf, $opts['layers'], $bg);
    $url = $this->baseurl.$this->delim.$qs;
    $data = @file_get_contents($url);
    if( $data === FALSE ) {
      // log error
      $err = error_get_last();
      error_log($err['message']);
    }
    else {
      // if http/https, $http_response_header is set.
      if( isset($http_response_header) ) {
        if( is_array($http_response_header) ) {
          foreach( $http_response_header as $line ) {
            if( preg_match('/^HTTP\\/[0-9\\.]+\\s+[0-9]+\\s+.*$/', $line) ) {
              $status = (int)preg_replace('/^HTTP\\/[0-9\\.]+\\s+([0-9]+)\\s+.*$/', '${1}', $line);
              if( $status != 200 ) {
                // error occurred
                error_log($url.' gets '.$status);
                $data = FALSE;
                break;
              }
            }
            if( preg_match('/^Content-Type\\s*:/', $line) ) {
              $ct = preg_replace('/^Content-Type\\s*:\\s*([^\\s].*)$/', '${1}', $line);
              $itc1 = ITCode::ct2itc($ct);
              if( $itc1 != $itc ) {
                error_log('Expects "'.ITCode::CONTENT_TYPES[$itc].'", but got contet type is "'.$ct.'".');
                $data = FALSE;
                break;
              }
            }
          }
        }
      }
    }
    return $data;
  }

}

// --------------------------------------------------------
// MSWMSSource - Source for WMS (with internal MapServer)
// --------------------------------------------------------
class MSWMSSource extends SourceBase {
  private $mspath;
  private $mapfilepath;
  private $cropper;

  public function __construct($mspath, $mapfilepath) {
    $this->mspath = $mspath;
    $this->mapfilepath = $mapfilepath;
    $this->cropper = new RasterCropper();
  }

  public function getCropper() {
    return $this->cropper;
  }

  public function getRaw($z, $x, $y, $itc, $pmx, $pmy, $prf, $opts, $bg = FALSE) {
    $qs = WMSQueryString::generate($z, $x, $y, $itc, $pmx, $pmy, $prf, $opts['layers'], $bg);
    $querystring = 'map='.$this->mapfilepath.'&'.$qs;
    $fr = popen("'".$this->mspath."' 'QUERY_STRING=${querystring}'", 'r');
    $data = FALSE;
    $fmt = ITCode::WMS_FORMATS[$itc];
    $line1 = fgets($fr);
    $line2 = fgets($fr);
    if( strpos($line1, 'Content-Type: '.$fmt) === 0 ) {
      // 前方一致
      $data = stream_get_contents($fr);
    }
    else if( strpos($line1, 'Content-Type: text/xml') === 0 ) {
      // error
      $error = stream_get_contents($fr);
      error_log( $error );
    }
    pclose($fr);
    return $data;
  }
}

// ----------------------------------------------------------------
// Store
//
abstract class StoreBase{
  abstract public function get($layername, $z, $x, $y, $itc);
  abstract public function put($layername, $z, $x, $y, $itc, $data);
}

abstract class FileStoreBase {
  private $basedir;
  abstract public function dirpath($layername,$z,$x,$y,$itc);
  abstract public function filename($layername,$z,$x,$y,$itc);

  public function get($layername, $z, $x, $y, $itc) {
    $filepath =
      $this->basedir.DIRECTORY_SEPARATOR.
      $this->dirpath($layername,$z,$x,$y,$itc).DIRECTORY_SEPARATOR.
      $this->filename($layername,$z,$x,$y,$itc);
    if( file_exists($filepath) ) {
      return file_get_contents($filepath);
    }
    return FALSE;
  }

  public function put($layername, $z, $x, $y, $itc, $data) {
    $dirpath =
      $this->basedir.DIRECTORY_SEPARATOR.
      $this->dirpath($layername,$z,$x,$y,$itc);
    $filepath = $dirpath.DIRECTORY_SEPARATOR.$this->filename($layername,$z,$x,$y,$itc);
    if( !is_dir($dirpath) ) {
      if( mkdir($dirpath, 0755, true) === FALSE ) {
        return FALSE;
      }
    }
    $r = file_put_contents($filepath, $data);
    return $r !== FALSE;
  }

  public function __construct($basedir) {
    $this->basedir = $basedir;
  }

}

class TMSStore extends FileStoreBase {
  public function dirpath($layername,$z,$x,$y,$itc) {
    return
      $layername.DIRECTORY_SEPARATOR.
      $z.DIRECTORY_SEPARATOR.
      $x;
  }

  public function filename($layername,$z,$x,$y,$itc) {
    return $y.'.'.ITCode::EXTENSIONS[$itc];
  }
}

class DigitStore extends FileStoreBase {
  private $digits;
  private $fmt;
  private $fmt2;

  public function __construct($basedir, $digits) {
    parent::__construct($basedir);
    $this->digits = $digits;
    $this->fmt = sprintf('%%0%dd', $digits);
    $this->fmt2 = $this->fmt.$this->fmt;
  }

  public function dirpath($layername,$z,$x,$y,$itc) {
    $ret = $layername.DIRECTORY_SEPARATOR.$z;
    $ax = str_split(sprintf($this->fmt, $x));
    $ay = str_split(sprintf($this->fmt, $y));
    for( $n = 0; $n < $this->digits-1; $n++ ) {
      $ret = $ret.DIRECTORY_SEPARATOR.$ax[$n].$ay[$n];
    }
    return $ret;
  }

  public function filename($layername,$z,$x,$y,$itc) {
    return sprintf($this->fmt2, $x, $y).'.'.ITCode::EXTENSIONS[$itc];
  }
}

class PGStore extends StoreBase {
  private $connstr;
  private $table;

  public function get($layername, $z, $x, $y, $itc) {
    $conn = pg_connect($this->connstr);
    pg_query($conn, 'begin');
    $ret = FALSE;
    $res = pg_query_params(
      'SELECT data FROM '.$this->table.' '.
        'WHERE layer=$1 AND z=$2 AND x=$3 AND y=$4 AND itc=$5',
      array($layername,$z,$x,$y,$itc)
    );
    if( pg_numrows($res) > 0 ) {
      $oid = pg_fetch_result($res, 0, 0);
      if( $oid !== FALSE ) {
        $tmppath = tempnam( sys_get_temp_dir(), '_TILE_PG_READ_');
        if( pg_lo_export($conn, $oid, $tmppath) !== FALSE ) {
          $ret = file_get_contents($tmppath);
        }
      }
    }
    pg_query($conn, 'end');
    pg_close($conn);
    return $ret;
  }

  public function put($layername, $z, $x, $y, $itc, $data) {
    $conn = pg_connect($this->connstr);
    $tmppath = tempnam( sys_get_temp_dir(), '_TILE_PG_WRITE_');
    file_put_contents($tmppath, $data);
    pg_query($conn, 'begin');
    $ret = FALSE;
    $oid = pg_lo_import($conn, $tmppath);
    if( $oid !== FALSE ) {
      $resinsert = pg_query_params(
        $conn,
        'INSERT INTO '.$this->table.' (layer,z,x,y,itc,data) '.
          'SELECT $1,$2,$3,$4,$5,$6',
        array($layername,$z,$x,$y,$itc,$oid)
      );
      $ret = $resinsert !== FALSE;
    }
    pg_query($conn, 'end');
    pg_close($conn);
    unlink($tmppath);
    return $ret;
  }

  public function __construct($connstr, $table) {
    $this->connstr = $connstr;
    $this->table = $table;
  }
}

// ----------------------------------------------------------------
class Url {
  private $https; // true/false
  private $host;  // foo.bar.example
  private $port;  // 80, 443
  private $scrpath;  // /path/to/script
  public $segments; // /path/info/file_dir
  private $isdir; // true/false

  public function isdir() {
    return $this->isdir;
  }

  public function getSegments() {
    return $this->segments;
  }

  public function __construct($server = FALSE) {
    $this->https = FALSE;
    $this->host = FALSE;
    $this->port = FALSE;
    $this->scrpath = FALSE;
    $this->segments = FALSE;
    $this->isdir = FALSE;
    if( $server !== FALSE ) {
      $this->analyze($server);
    }
  }

  public function analyze($server) {
    // https
    if( isset($server['HTTPS']) ) {
      $this->https = TRUE;
    }
    else {
      $this->https = FALSE;
    }
    // host
    $this->host = $this->getHost($server);
    // port
    $this->port = $this->https ? 443 : 80;
    if( isset($_SERVER['SERVER_PORT']) ) {
      $port = $_SERVER['SERVER_PORT'];
      if( preg_match('/^[0-9]+$/',$port) ) {
        $this->port = $port * 1;
      }
    }
    // path (SCRIPT_FILENAME and PATH_INFO]
    // REQUEST_URI = /path/to/scr/path/info
    // SCRIPT_NAME = /path/to/scr.php
    // PATH_INFO = /path/info
    $scrpath = FALSE;
    $ru = $server['REQUEST_URI'];
    $rulen = strlen($ru);
    $pi = '';
    if( isset($server['PATH_INFO']) ) {
      $pi = $server['PATH_INFO'];
    }
    $pilen = strlen($pi);
    $sn = $server['SCRIPT_NAME'];
    $snlen = strlen($sn);
    if( $rulen > $pilen && substr($ru,$rulen-$pilen) == $pi ) {
      // ($rul = $ru) =~ s/(.*)PATH_INFO/\1/
      $rullen = $rulen - $pilen;
      $rul = substr($ru, 0, $rullen);
      if( $rullen <= $snlen && $rul == substr($sn,0,$rullen) ) {
        // ($snc = $sn) =~ s/$rul(.*)/\1/
        // Expects ".php" exists
        $snc = substr($sn,$rullen,$snlen-$rullen);
        if( preg_match('/^\\.[_a-zA-Z][_a-zA-Z0-9]*$/', $snc) ) {
          // Uses REQUEST_URI
          $scrpath = $rul;
        }
      }
    }
    if( $scrpath !== FALSE ) {
      $this->scrpath = $scrpath;
    }
    else {
      $this->scrpath = $sn;
    }
    // pathinfo
    // isdir
    if( !($pilen > 0) ) {
      // no pathinfo
      $this->segments = FALSE;
      $this->isdir = FALSE;
    }
    else {
      if( substr($pi,0,1) != '/' ) {
        $pi = '/'.$pi;
      }
      $this->segments = explode('/',$pi);
      if( substr($pi,-1) == '/' ) {
        $this->isdir = TRUE;
        array_pop($this->segments);
      }
      else {
        $this->isdir = FALSE;
      }
    }
  }

  public function buildRootPath($addtail = FALSE) {
    $ret = $this->scrpath;
    if( $addtail ) {
      $ret = $ret.'/';
    }
    return $ret;
  }

  public function buildRootUrl($addtail = FALSE) {
    $ret = ($this->https ? 'https' : 'http').'://'.$this->host;
    if( !($this->https && $this->port==443) && !(!$this->https && $this->port==80) ) {
      $ret = $ret.':'.$this->port;
    }
    return $ret.$this->buildRootPath($addtail);
  }

  public function buildThisUrl($forcedir) {
    $ret = $this->buildRootUrl();
    if( $this->segments !== FALSE ) {
      $ret = $ret .implode('/', $this->segments);
    }
    return $ret.(($adddir || $this->isdir) ? '/' : '');
  }

  //
  // Gets the hostname $_SERVER hash.
  //  If forwarded, uses HTTP_X_FORWARDED_SERVER.
  //  Otherwise, uses SERVER_NAME.
  // If you want to use other information for hostname,
  // override this.
  //
  public function getHost($server) {
    if( isset($server['HTTP_X_FORWARDED_SERVER']) ) {
      return $server['HTTP_X_FORWARDED_SERVER'];
    }
    return $server['SERVER_NAME'];
  }
}

//
// TileParams
//
class TileParams {

  const T_NONE    = 0;       //  0
  const T_ROOT     = 0x0011; // 17 /
  const T_TOROOT   = 0x0001; //  1 BACK TO ROOT
  const T_WMTS     = 0x0101; // /WMTSCapabilities.xml
  const T_TMSSRV   = 0x0102; // /tilemapservice.xml
  const T_LAYER    = 0x0111; // 273 /{L}/
  const T_KMLROOT  = 0x0201; // 513 /{L}/doc.{E}.kml
  const T_TILEJSON = 0x0202; // 514 /{L}/tilejson.{E}.json
  const T_TMSRES   = 0x0203; // 515 /{L}/tilemapresource.xml
  const T_Z        = 0x0211; // /{L}/{Z}/
  const T_X        = 0x0311; // /{L}/{Z}/{X}
  const T_IMAGE    = 0x0401; // 1025 /{L}/{Z}/{X}/{Y}.{E}
  const T_KML      = 0x0402; // 1026 /{L}/{Z}/{X}/{Y}.{E}.kml
  const T_ERROR    = 0x1FFF; // Error
  const T_NOTFOUND = 0x1001; // 4097 Error: notfound
  const T_FORBIDDEN= 0x1002; // Error: forbidden

  private $url;
  private $type;
  private $layername;
  private $z; // Z value
  private $x; // X value
  private $y; // Y value
  private $itc; // Image Type Code

  public function __construct($url = FALSE) {
    $this->url = $url;
    $this->type = FALSE;
    $this->layername = FALSE;
    $this->z = FALSE;
    $this->x = FALSE;
    $this->y = FALSE;
    if( $url ) {
      $this->assign($url);
    }
  }

  public function needsRedirection() {
    return ($this->type & 0x0010) && !($this->url->isdir());
  }

  public function getType() {
    return $this->type;
  }

  public function getImageTypeCode() {
    return $this->itc;
  }

  public function getZ() {
    return $this->z;
  }

  public function getX() {
    return $this->x;
  }

  public function getY() {
    return $this->y;
  }

  public function getContentType() {
    switch($this->type) {
    case TileParams::T_WMTS:
      return ITCode::CONTENT_TYPES[ITCode::IT_XML];
    case TileParams::T_TMSSRV:
      return ITCode::CONTENT_TYPES[ITCode::IT_XML];
    case TileParams::T_KMLROOT:
      return ITCode::CONTENT_TYPES[ITCode::IT_KML];
    case TileParams::T_TILEJSON:
      return ITCode::CONTENT_TYPES[ITCode::IT_JSON];
    case TileParams::T_TMSRES:
      return ITCode::CONTENT_TYPES[ITCode::IT_XML];
    case TileParams::T_IMAGE:
      return ITCode::CONTENT_TYPES[$this->itc];
    case TileParams::T_KML:
      return ITCode::CONTENT_TYPES[ITCode::IT_KML];
    }
    return ITCode::CONTENT_TYPES[ITCode::IT_HTML];
  }

  public function getLayerName() {
    return $this->layername;
  }

  public function buildThisUrl($forcedir) {
    return $this->url->buildThisUrl($forcedir);
  }

  public function buildRootUrl($forcedir) {
    return $this->url->buildRootUrl($forcedir);
  }

  public function buildLayerUrl($addtail = FALSE, $layername = FALSE) {
    if( $layername === FALSE ) {
      $layername = $this->layername;
    }
    $ret = $this->buildRootUrl(TRUE).$layername;
    if( $addtail ) {
      $ret = $ret.'/';
    }
    return $ret;
  }

  public function buildWmtsUrl() {
    return $this->buildRootUrl(TRUE).'WMTSCapabilities.xml';
  }

  public function buildKmlRootUrl($itc, $layername = FALSE) {
    $layerurl = $this->buildLayerUrl(TRUE, $layername);
    return $layerurl . 'doc.'.ITCode::EXTENSIONS[$itc].'.kml';
  }

  public function buildTileJsonUrl($itc, $layername = FALSE) {
    $layerurl = $this->buildLayerUrl(TRUE, $layername);
    return $layerurl . 'tilejson.'.ITCode::EXTENSIONS[$itc].'.json';
  }

  public function buildTileUrl($z, $x, $y, $type, $itc, $layername=FALSE) {
    $ret = $this->buildLayerUrl(TRUE, $layername).$z.'/'.$x.'/'.$y.'.'.ITCode::EXTENSIONS[$itc];
    if( $type == TileParams::T_KML ) {
      $ret = $ret.'.kml';
    }
    return $ret;
  }

  private function cksegs($segs, $start, $count) {
    $ret = TileParams::T_NOTFOUND;
    $tail = $start + $count;
    $fin = FALSE;
    for( $n = $start; $n < $tail; $n++ ) {
      if( $fin ) {
        // If $fin is true, reads path fully at last iteraction.
        return TileParams::T_NOTFOUND;
      }
      $rone = TileParams::T_NOTFOUND;
      $seg = $segs[$n];
      switch( $n ) {
      case 4:
        if( preg_match('/^-?[0-9]+\\.[a-zA-z]+$/', $seg) ) {
          $rone = TileParams::T_IMAGE;
        }
        else if( preg_match('/^-?[0-9]+\\.[a-zA-z]+\\.kml$/', $seg) ) {
          $rone = TileParams::T_KML;
        }
        $fin = TRUE;
        break;
      case 3:
        if( preg_match('/^-?[0-9]+$/', $seg) ) {
          $rone = TileParams::T_X;
        }
        break;
      case 2:
        if( preg_match('/^-?[0-9]+$/', $seg) ) {
          $rone = TileParams::T_Z;
        }
        else if( $seg == 'tilemapresource.xml' ) {
          $rone = TileParams::T_TMSRES;
          $fin = TRUE;
        }
        else {
          if( preg_match('/^doc\\.[a-zA-z]+\\.kml$/', $seg) ) {
            $rone = TileParams::T_KMLROOT;
            $fin = TRUE;
          }
          else if( preg_match('/^tilejson\\.[a-zA-z]+\\.json$/', $seg) ) {
            $rone = TileParams::T_TILEJSON;
            $fin = TRUE;
          }
          else {
            $rone = TileParams::T_LAYER;
          }
        }
        break;
      case 1:
        if( $seg == 'WMTSCapabilities.xml' ) {
          $rone = TileParams::T_WMTS;
          $fin = TRUE;
        }
        else if( $seg== 'tilemapservice.xml' ) {
          $rone = TileParams::T_TMSSRV;
          $fin = TRUE;
        }
        else {
          $rone = TileParams::T_LAYER;
        }
        break;
      case 0:
        if( $seg == '' ) {
          $rone = TileParams::T_ROOT;
        }
        break;
      }
      if( $rone == TileParams::T_NOTFOUND ) {
        return TileParams::T_NOTFOUND;
      }
      else {
        $ret = $rone;
      }
    }
    return $ret;
  }

  public function assign($url) {
    // local
    $this->type = FALSE;
    $this->itc = FALSE;
    $this->layername = FALSE;
    // segment (pathinfo)
    $segs = $url->getSegments();
    $seglen = $segs !== FALSE ? count($segs) : 0;
    if( $seglen == 0 ) {
      // to root
      $this->type = TileParams::T_TOROOT;
    }
    else {
      $this->type = $this->cksegs($segs, 0, $seglen);
    }
    // {Y}.{E}
    switch( $this->type ) {
    case TileParams::T_KML: // {Y}.{E}.kml
    case TileParams::T_IMAGE: // {Y}.{E}
      $af = explode('.', $segs[4]);
      $this->y = $af[0] * 1;
      $this->itc = ITCode::ext2itc($af[1]);
      break;
    }
    // {X}
    switch( $this->type ) {
    case TileParams::T_KML:
    case TileParams::T_IMAGE:
    case TileParams::T_X:
      $this->x = $segs[3] * 1;
    }
    // {Z}
    switch( $this->type ) {
    case TileParams::T_KML:
    case TileParams::T_IMAGE:
    case TileParams::T_X:
    case TileParams::T_Z:
      $this->z = $segs[2] * 1;
    }
    // {L}/KMLROOT (doc.[imagetype].kml)
    switch( $this->type ) {
    case TileParams::T_KMLROOT:
    case TileParams::T_TILEJSON:
      $af = explode('.', $segs[2]);
      $this->itc = ITCode::ext2itc($af[1]);
      break;
    }
    // {L}
    switch( $this->type ) {
    case TileParams::T_KML:
    case TileParams::T_IMAGE:
    case TileParams::T_X:
    case TileParams::T_Z:
    case TileParams::T_TMSRES:
    case TileParams::T_KMLROOT:
    case TileParams::T_TILEJSON:
    case TileParams::T_LAYER:
      $this->layername = $segs[1];
    }
    // (root)
    switch( $this->type ) {
    case TileParams::T_KML:
    case TileParams::T_IMAGE:
    case TileParams::T_X:
    case TileParams::T_Z:
    case TileParams::T_TMSRES:
    case TileParams::T_KMLROOT:
    case TileParams::T_TILEJSON:
    case TileParams::T_LAYER:
    case TileParams::T_WMTS:
    case TileParams::T_TMSSRV:
    case TileParams::T_ROOT:
    case TileParams::T_TOROOT:
      break;
    default:
      // NOT FOUND
      $this->type = TileParams::T_NOTFOUND;
    }
    return $this->type;
  }
}


abstract class CheckerBase {
  abstract public function check($prf, $z, $x, $y, $pmx, $pmy);
}

class PGChecker {
  private $connstr;
  private $tables_columns;
  public function __construct($connstr, $tables_columns) {
    $this->connstr = $connstr;
    $this->tables_columns = $tables_columns;
  }

  public function check($prf, $z, $x, $y, $pmx, $pmy) {
    // WITH argraw AS (<polygon> AS g),
    //  arg AS (g_4326, g_3857, ...)
    // SELECT EXISTS(SELECT * FROM <table>,arg WHERE g_<srs> && the_geom
    $arr = array();
    $srss = array();
    foreach($this->tables_columns as $tc) {
      $t = $tc[0];
      $c = $tc[1];
      $s = $tc[2]; // srs
      $gc = 'arg.g_'.$s; // g_(srs) of arg.
      if( in_array($s, $srss) === FALSE ) {
        array_push($srss, $s);
      }
      array_push($arr,
        'EXISTS(SELECT * FROM '.$t.',arg WHERE '.$c.' && '.$gc.')'
      );
    }
    // with clause
    $args = array();
    foreach( $srss as $srs ) {
      $gc = 'g_'.$srs;
      array_push($args, 'ST_Transform(g,'.$srs.') AS '.$gc);
    }
    $with = 'WITH argraw AS (SELECT $1::GEOMETRY AS g), '.
      'arg AS (SELECT '.implode(',',$args).' FROM argraw)';
    $query = $with.' SELECT '.implode(' OR ', $arr);
    $ub = $prf->t2ub($z, $x, $y, $pmx, $pmy); // box with margin (UNIT)
    $ub = $prf->intersection($ub); // 2016/12/04 force the box within the world.
    $ewkb = $ub->ewkb($prf->getCrs());
    // run
    $ret = TRUE;
    $conn = pg_connect($this->connstr);
    if( $conn !== FALSE ) {
      $res = pg_query_params($query, array(bin2hex($ewkb)));
      if( $res !== FALSE ) {
        $v = pg_fetch_result($res, 0, 0);
        if( $v == 'f' ) {
          $ret = FALSE;
        }
      }
    }
    return $ret;
  }
}



// ----------------------------------------------------------------
//
// Layer
//
class Layer {

  const ST_ERROR = 0;
  const ST_OK = 1;
  const ST_CACHE = 2;
  const ST_OB = 3;
  const ST_CHECK = 4;
  const ST_NOTFOUND = 5;

  private $name;
  private $prof;
  private $uext;
  private $itcs;
  private $llext;
  private $zext;
  private $pm; // pixel mergin
  private $source;
  private $store;
  private $checker;
  private $opts;

  public function getName() {
    return $this->name;
  }

  public function getZMin() {
    return $this->zext->zmin;
  }

  // minimum of available z (including z where 'ob' image must be returned)
  public function getAZMin() {
    if( $this->opts ) {
      if( isset($this->opts['ob']) ) {
        return 0;
      }
    }
    return $this->zext->zmin;
  }

  public function getZMax() {
    return $this->zext->zmax;
  }

  public function getLonLatExtent() {
    return $this->llext;
  }

  public function getTileExtent($z, $pmx, $pmy) {
    return $this->prof->t2llb($z, $this->llext, $pmx, $pmy);
  }

  public function getProfile() {
    return $this->prof;
  }

  public function getImageTypeCodes() {
    return $this->itcs;
  }

  public function __construct($name, $prof, $itcs, $llext, $zext, $pm, $source, $store, $checker, $opts) {
    $this->name = $name;
    $this->prof = $prof;
    $this->itcs = $itcs;
    $this->llext = $llext;
    $this->zext = $zext;
    $this->pm = $pm;
    $this->source = $source;
    $this->store = $store;
    $this->checker = $checker;
    $this->opts = $opts;
  }

  public function getWMTSLayer($params) {
    $name = $this->name;
    $llext = $this->getLonLatExtent();
    $xmin = $llext->xmin;
    $ymin = $llext->ymin;
    $xmax = $llext->xmax;
    $ymax = $llext->ymax;
    $zmin = $this->getAZMin();
    $zmax = $this->getZMax();
    $profname = $this->prof->getName();
    $ret = <<< EOL_LAYER_HEAD
    <Layer>
      <ows:Title>$name</ows:Title>
      <ows:WGS84BoundingBox>
        <ows:LowerCorner>$xmin $ymin</ows:LowerCorner>
        <ows:UpperCorner>$xmax $ymax</ows:UpperCorner>
      </ows:WGS84BoundingBox>
      <ows:Identifier>$name</ows:Identifier>
      <Style isDefault="true">
        <ows:Title>default</ows:Title>
        <ows:Identifier>default</ows:Identifier>
      </Style>
      <TileMatrixSetLink>
        <TileMatrixSet>MATRIXSET:$profname</TileMatrixSet>

EOL_LAYER_HEAD;
    for( $z = $zmin; $z <= $zmax; $z++ ) {
      $tb = $this->prof->llb2tb($z, $llext, 0, 0);
      $txmin = $tb->xmin;
      $tymin = $tb->ymin;
      $txmax = $tb->xmax;
      $tymax = $tb->ymax;
      $ret = $ret. <<< EOL_LAYER_LIMITS
          <TileMatrixSetLimits>
            <TileMatrix>$z</TileMatrix>
            <MinTileCol>$txmin</MinTileCol>
            <MinTileRow>$tymin</MinTileRow>
            <MaxTileCol>$txmax</MaxTileCol>
            <MaxTileRow>$tymax</MaxTileRow>
          </TileMatrixSetLimits>

EOL_LAYER_LIMITS;
    }
    $ret = $ret."      </TileMatrixSetLink>\n";
    // itc FORMAT
    foreach( $this->getImageTypeCodes() as $itc ) {
      $ct = ITCode::CONTENT_TYPES[$itc];
      $ret = $ret."      <Format>$ct</Format>\n";
    }
    // itc URL
    foreach( $this->getImageTypeCodes() as $itc ) {
      $url = $params->buildTileUrl('{TileMatrix}','{TileCol}','{TileRow}',TileParams::T_IMAGE, $itc, $name);
      $ct = ITCode::CONTENT_TYPES[$itc];
      $ret = $ret."      <ResourceURL format=\"$ct\" resourceType=\"tile\" template=\"".$url."\" />\n";
    }
    $ret = $ret."    </Layer>\n";
    return $ret;
  }

  public function getfile($path) {
    if( substr($path, 0, 1) != DIRECTORY_SEPARATOR ) {
      $path = __DIR__.DIRECTORY_SEPARATOR.$path;
    }
    return file_get_contents($path);
  }

  public function hasItc($itc_arg) {
    foreach( $this->itcs as $itc_layer ) {
      if( $itc_layer == $itc_arg ) {
        // hit
        return TRUE;
      }
    }
    return FALSE;
  }

  public function getImage($z, $x, $y, $itc) {
    $pmx = 0;
    $pmy = 0;
    if( $this->pm ) {
      $pmx = $this->pm->x;
      $pmy = $this->pm->y;
    }
    if(
      (
        $this->llext
        && !Box::intersects(
          $this->prof->prjbox($this->llext),
          $this->prof->t2ub($z, $x, $y, 0, 0)
        )
      )
      || (
        $this->zext
        && (
          $z < $this->zext->zmin
          || $z > $this->zext->zmax
        )
      )
    ) {
      // out of bounds
      $data = FALSE;
      if( $this->opts ) {
        if( isset($this->opts['ob']) ) {
          if( isset($this->opts['ob'][$itc]) ) {
            $data = $this->getfile($this->opts['ob'][$itc]);
          }
        }
      }
      return [self::ST_OB, $data];
    }
    if( $this->checker && !$this->checker->check($this->prof, $z, $x, $y, $pmx, $pmy) ) {
      // checker error
      if( $this->opts ) {
        if( isset($this->opts['blank']) ) {
          if( isset($this->opts['blank'][$itc]) ) {
            $data = $this->getfile($this->opts['blank'][$itc]);
          }
        }
      }
      return [self::ST_OB, $data];
    }
    if( $this->store ) {
      $data = $this->store->get($this->name, $z, $x, $y, $itc);
      if( $data !== FALSE ) {
        return [self::ST_CACHE, $data];
      }
    }
    if( $this->source ) {
      $data = $this->source->get($z,$x,$y,$itc, $pmx, $pmy, $this->prof, $this->opts);
      if( $data !== FALSE ) {
        if( $this->store ) {
          $this->store->put($this->name, $z, $x, $y, $itc, $data);
        }
        return [self::ST_OK, $data];
      }
    }
    return  [self::ST_ERROR, FALSE];
  }

  function getKmlNetworkLink($params, $z, $itc, $tb) {
    $ret = '';
    for( $y = $tb->ymin; $y <= $tb->ymax; $y++ ) {
      for( $x = $tb->xmin; $x <= $tb->xmax; $x++ ) {
        $llb = $this->prof->t2llb($z, $x, $y, 0, 0);
        $west = $llb->xmin;
        $south = $llb->ymin;
        $east = $llb->xmax;
        $north = $llb->ymax;
        $kml_name = $params->buildTileUrl($z, $x, $y, TileParams::T_IMAGE, $itc);
        $kml_href = $params->buildTileUrl($z, $x, $y, TileParams::T_KML, $itc);
        $networklink =<<< EOL_LINK
<NetworkLink>
  <name>$kml_name</name>
  <Region>
    <Lod>
      <minLodPixels>128</minLodPixels>
      <maxLodPixels>-1</maxLodPixels>
    </Lod>
    <LatLonAltBox>
      <north>$north</north>
      <south>$south</south>
      <west>$west</west>
      <east>$east</east>
    </LatLonAltBox>
  </Region>
  <Link>
    <href>$kml_href</href>
    <viewRefreshMode>onRegion</viewRefreshMode>
    <viewFormat></viewFormat>
  </Link>
</NetworkLink>

EOL_LINK;
        $ret = $ret.$networklink;
      }
    }
    return $ret;
  }


  function getKmlRoot($params, $name, $itc) {
    // lookat (optional)
    $lookat = '';
    if( $this->llext ) {
      $c = $this->llext->centroid();
      $clon = $c->x;
      $clat = $c->y;
      $alti = 0.0;
      // range
      $R = 6378137.0;
      $lonmin = $this->llext->xmin;
      $lonmax = $this->llext->xmax;
      $latmin = $this->llext->ymin;
      $latmax = $this->llext->ymax;
      $dlon = $lonmax - $lonmin;
      $dlat = $latmax - $latmin;
      $dlam = $dlon*M_PI/180.0;
      $dphi = $dlat*M_PI/180.0;
      // $alat = abs(lat)
      $alatmax = abs($latmax);
      $alatmin = abs($latmin);
      $alat = $alatmax > $alatmin ? $alatmin : $alatmax;
      // Focal Length (mm)
      $F = 30.0;
      // range for y
      $tan_ty = 18.0 / $F;
      $range_lat = $R * (1-cos(0.5*$dlam)+sin(0.5*$dlam)/$tan_ty);
      // range for x
      $tan_tx = 12.0 / $F;
      $range_lon = $R * cos($alat*M_PI/180.0) * (1-cos(0.5*$dlam)+sin(0.5*$dlam)/$tan_tx);
      // range
      $range = $range_lat > $range_lon ? $range_lat : $range_lon;
      // LookAt element
      $lookat = <<< EOL_LOOKAT
<LookAt>
  <latitude>$clat</latitude>
  <longitude>$clon</longitude>
  <altitude>0</altitude>
  <range>$range</range>
</LookAt>

EOL_LOOKAT;
    }
    // networklink
    $tb = $this->prof->ub2tb($this->zext->zmin, $this->llext, 0, 0);
    $networklink = $this->getKmlNetworkLink($params, $this->zext->zmin, $itc, $tb);
    // Build
    return <<< EOL_KML
<?xml version="1.0" ?>
<kml xmlns="http://earth.google.com/kml/2.1">
<Document>
<Name>$name</Name>
<Description></Description>
<Style>
  <ListStyle id="hideChildren">
    <listItemType>checkHideChildren</listItemType>
  </ListStyle>
</Style>
$lookat
$networklink
</Document>
</kml>

EOL_KML;
  }

  function getKml($params, $name, $z, $x, $y, $itc) {
    $st = self::ST_OK;
    if(
      (
        $this->llext
        && !Box::intersects(
          $this->prof->prjbox($this->llext),
          $this->prof->t2ub($z, $x, $y, 0, 0)
        )
      )
      || (
        $this->zext
        && (
          $z < $this->zext->zmin
          || $z > $this->zext->zmax
        )
      )
    ) {
      // out of bounds
      $st = self::ST_OB;
      if( !$this->opts ) {
        if( isset($this->opts['ob']) ) {
          if( isset($this->opts['ob'][$itc]) ) {
            // has alternative
            $st = self::ST_OK;
          }
        }
      }
    }
    if( $st != self::ST_OK ) {
      return [$st, FALSE];
    }
    // -- Networklink
    $networklink = '';
    if( $z + 1 <= $this->zext->zmax ) {
      // Tile box on z-1
      $tbz1 = Box::intersection(
        new Box(2*$x, 2*$y, 2*$x+1, 2*$y+1),
        $this->prof->llb2tb($z+1, $this->llext, 0, 0)
      );
      $networklink = $this->getKmlNetworkLink($params, $z+1, $itc, $tbz1);
    }
    // -- Ground overrlay
    $img_href = $params->buildTileUrl($z, $x, $y, TileParams::T_IMAGE, $itc);
    $llb = $this->prof->t2llb($z, $x, $y, 0, 0);
    $west = $llb->xmin;
    $south = $llb->ymin;
    $east = $llb->xmax;
    $north = $llb->ymax;
    $groundoverlay =<<< EOL_GO
<GroundOverlay>
  <Icon>
    <href>$img_href</href>
  </Icon>
  <Lod>
    <minLodPixels>128</minLodPixels>
    <maxLodPixels>-1</maxLodPixels>
  </Lod>
  <LatLonBox>
    <north>$north</north>
    <south>$south</south>
    <west>$west</west>
    <east>$east</east>
  </LatLonBox>
</GroundOverlay>

EOL_GO;
    // ret
    return <<< EOL_KML
<?xml version="1.0" ?>
<kml xmlns="http://earth.google.com/kml/2.1">
<Document>
<Name>0/1/0.png.kml</Name>
<Description></Description>
<Style>
  <ListStyle id="hideChildren">
    <listItemType>checkHideChildren</listItemType>
  </ListStyle>
</Style>
<Region>
<Lod>
  <minLodPixels>128</minLodPixels>
  <maxLodPixels>-1</maxLodPixels>
</Lod>
<LatLonAltBox>
  <north>$north</north>
  <south>$south</south>
  <east>$east</east>
  <west>$west</west>
  
</LatLonAltBox>
</Region>
$groundoverlay
$networklink
</Document>
</kml>

EOL_KML;
  }

}


// ----------------------------------------------------------------
//
// TileCache
//
class TileCache {
  private $name; // Name of this application.
  private $layers; // ASSOCIATIVE array of layers.

  //
  // output
  //
  public static function showContent($params, $content) {
    Header('Content-Type: '.($params->getContentType()));
    echo $content;
  }

  public static function showRootRedirection($params) {
    Header('Location: '.($params->buildRootUrl(TRUE)));
  }

  public static function showRedirection($params) {
    Header('Location: '.($params->buildThisUrl(TRUE)));
  }

  public static function showHtml($title, $head, $body) {
    Header('Content-Type: '.ITCode::CONTENT_TYPES[ITCode::IT_HTML]);
    echo <<< EOL_HTML
<!DOCTYPE html>
<html>
<head>
  <meta cahrset="UTF-8">
  <title>$title</title>
  $head
</head>
<body>
<h1>$title</h1>
$body
</body>
</html>
EOL_HTML;

  }

  public static function showNotFound($params) {
    TileCache::showError(
      404,
      'Not Found',
      array(
        'The requested URL was not found on this server.'
      )
    );
  }

  public static function showForbidden($params) {
    TileCache::showError(
      403,
      'Forbidden',
      array(
        'You don\'t have permission to access the requested resource on this server.'
      )
    );
  }

  public static function showInternalServerError($params) {
    TileCache::showError(
      500,
      'Internal Server Error',
      array(
        'The server encountered an internal error or misconfiguration and was unable to complete your request.',
        'Please contact the server administrator to inform them of the time this error occurred, and the actions you performed just before this error.',
        'More information about this error may be available in the server error log.'
      )
    );
  }

  public static function showError($code, $title, $arrmess) {
    http_response_code($code);
    echo <<< EOL_ERROR_HEAD
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>$code $title</title>
</head><body>
<h1>$title</h1>

EOL_ERROR_HEAD;
    foreach($arrmess as $mess) {
      echo "<p>$mess</p>\n";
    }
    echo "</body></html>\n";
  }

  // @param $name Name of this application.
  // @param $layers Array (not associative) of layers.
  public function __construct($name, $layers) {
    $this->name = $name;
    $this->layers = array();
    foreach( $layers as $layer ) {
      $this->layers[$layer->getName()] = $layer;
    }
  }


  //
  // Gets the layer bye the parameter (which contains layername)
  //
  public function getLayer($params) {
    $layername = $params->getLayerName();
    // Gets layer by layername
    if( !isset($this->layers[$layername]) ) {
      return FALSE;
    }
    return $this->layers[$layername];
  }

  //
  // process
  //

  //
  // Process at root URL. Simply shows a table.
  //
  function processRoot($params) {
    $body = "<table><tbody>\n<tr><th>Name</th><th>Extension</th><th>z</th><th>KML</th><th>TileJSON</th><th>Pattern</th></tr>\n";
    foreach( $this->layers as $layername => $layer ) {
      $itcs =  $layer->getImageTypeCodes();
      $itcslen = count($itcs);
      if( count($itcs) > 0 ) {
        $body = $body . "<tr><th rowspan=\"$itcslen\">$layername</th>";
        for( $n = 0; $n < $itcslen; $n++ ) {
          $itc = $itcs[$n];
          if( $n > 0 ) {
            $body = $body . '<tr>';
          }
          $ext = ITCode::EXTENSIONS[$itc];
          $pattern = $params->buildTileUrl('{z}','{x}','{y}', TileParams::T_IMAGE, $itc, $layername);
          $kmlroot = $params->buildKmlRootUrl($itc, $layername);
          $tilejson = $params->buildTileJsonUrl($itc, $layername);
          $z = ($layer->getZMin().'-'.$layer->getZMax());
          $body = $body
            . "<th>$ext</th><td>$z</td><td><a href=\"$kmlroot\">KML</a></td><td><a href=\"$tilejson\">TileJSON</a></td><td>$pattern</td></tr>\n";
        }
      }
      else {
        $body = $body . "<tr><th>$layername</th><td></td><td></td></tr>\n";
      }
    }
    $body = $body . "</tbody></table>\n";
    // WMTSCapabilities.xml
    $body = $body . '<p><a href="'.($params->buildWmtsUrl()).'">WMTSCapabilities.xml</p>';
    TileCache::showHtml($this->name, '', $body);
  }


  function processImage($layer, $params) {
    $itc = $params->getImageTypeCode();
    $data = $layer->getImage($params->getZ(), $params->getX(), $params->getY(), $params->getImageTypeCode());
    if( $data[1] !== FALSE ) {
      TileCache::showContent($params, $data[1]);
    }
    else if( $data[0] == Layer::ST_OB ){
      TileCache::showNotFound($params);
    }
    else {
      TileCache::showInternalServerError($params);
    }
  }

  function processKml($layer, $params) {
    $itc = $params->getImageTypeCode();
    $data = $layer->getKml($params, $layer->getName(), $params->getZ(), $params->getX(), $params->getY(), $params->getImageTypeCode());
    TileCache::showContent($params, $data);
  }

  function processKmlRoot($layer, $params) {
    $itc = $params->getImageTypeCode();
    $data = $layer->getKmlRoot($params, $layer->getName(), $params->getImageTypeCode());
    TileCache::showContent($params, $data);
  }

  function processTileJSON($layer, $params) {
    $name = $layer->getName();
    $scheme = 'xyz';
    $minzoom = $layer->getZMin();
    $maxzoom = $layer->getZMax();
    $bounds = $layer->getLonLatExtent()->toArray();
    $tiles = array($params->buildTileUrl('{z}','{x}','{y}',$params->getType(),$params->getImageTypeCode()));
    $arr = array(
      "tilejson" => "2.1.0",
      "name" => $name,
//      "description": $desc,
//      "attribution": $attr,
      "scheme" => $scheme,
      "tiles" => $tiles,
      "minzoom" => $minzoom,
      "maxzoom" => $maxzoom,
      "bounds" => $bounds
    );
    $data = json_encode($arr);
    TileCache::showContent($params, $data);
  }

  function processWMTS($params) {
    $name = $this->name;
    $url_root = $params->buildRootUrl(TRUE);
    $url_wmts = $url_root.'WMTSCapabilities.xml';
    // head
    $data = <<< EOL_XML_HEAD
<?xml version="1.0" encoding="UTF-8" ?>
<Capabilities xmlns="http://www.opengis.net/wmts/1.0" xmlns:ows="http://www.opengis.net/ows/1.1" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.opengis.net/wmts/1.0 http://schemas.opengis.net/wmts/1.0/wmtsGetCapabilities_response.xsd" version="1.0.0">
  <ows:ServiceIdentification>
    <ows:Title>$name</ows:Title>
    <ows:ServiceType>OGC WMTS</ows:ServiceType>
    <ows:ServiceTypeVersion>1.0.0</ows:ServiceTypeVersion>
  </ows:ServiceIdentification>
  <ows:OperationsMetadata>
    <ows:Operation name="GetCapabilities">
      <ows:DCP>
        <ows:HTTP>
          <ows:Get xlink:href="$url_wmts">
            <ows:Constraint name="GetEncoding">
              <ows:AllowedValues>
                <ows:Value>RESTful</ows:Value>
              </ows:AllowedValues>
            </ows:Constraint>
          </ows:Get>
        </ows:HTTP>
      </ows:DCP>
    </ows:Operation>
    <ows:Operation name="GetTile">
      <ows:DCP>
        <ows:HTTP>
          <ows:Get xlink:href="$url_root">
            <ows:Constraint name="GetEncoding">
              <ows:AllowedValues>
                <ows:Value>RESTful</ows:Value>
              </ows:AllowedValues>
            </ows:Constraint>
          </ows:Get>
        </ows:HTTP>
      </ows:DCP>
    </ows:Operation>
  </ows:OperationsMetadata>
  <Contents>

EOL_XML_HEAD;
    // each layer
    foreach( $this->layers as $name => $layer ) {
      $data = $data . $layer->getWMTSLayer($params);
    }
    // TileMatrixSet
    // Collects matrixsets
    $mss = array();
    foreach( $this->layers as $name => $layer ) {
      $prof = $layer->getProfile();
      $profname = $prof->getName();
      $zmin = $layer->getAZMin();
      $zmax = $layer->getZMax();
      if( !isset($mss[$profname]) ) {
        $mss[$profname] = array($prof, $zmin, $zmax);
      }
      else {
        if( $zmin < $mss[$profname][1] ) {
          $mss[$profname][1] = $zmin;
        }
        if( $zmax > $mss[$profname][2] ) {
          $mss[$profname][2] = $zmax;
        }
      }
    }
    // gets tilematrixset
    foreach( $mss as $profname => $profset ) {
      $prof = $profset[0];
      $zmin = $profset[1];
      $zmax = $profset[2];
      $crslong = $prof->getCRSLong();
      $scaleset = $prof->getWellKnownScaleSet();
      $pts = $prof->getTileSize();
      $ptsx = $pts->x;
      $ptsy = $pts->y;
      $lefttop = $prof->getUnitLeftTopCorner()->join(' ');
      $data = $data . <<< EOL_TILEMATRIXSET_HEAD
    <TileMatrixSet>
      <ows:Identifier>MATRIXSET:$profname</ows:Identifier>
      <ows:SupportedCRS>$crslong</ows:SupportedCRS>
      <WellKnownScaleSet>$scaleset</WellKnownScaleSet>

EOL_TILEMATRIXSET_HEAD;
      // TileMatrix (each Z)
      for( $z = $zmin; $z <= $zmax; $z++ ) {
        $tb = $prof->getTileExtent($z, 0, 0);
        $tw = $tb->xmax-$tb->xmin+1;
        $th = $tb->ymax-$tb->ymin+1;
        $scaledenom = $prof->getOGCScaleDenominator($z);
        $data = $data . <<< EOL_TILEMATRIX
      <TileMatrix>
        <ows:Identifier>$z</ows:Identifier>
        <TopLeftCorner>$lefttop</TopLeftCorner>
        <ScaleDenominator>$scaledenom</ScaleDenominator>
        <TileWidth>$ptsx</TileWidth>
        <TileHeight>$ptsy</TileHeight>
        <MatrixWidth>$tw</MatrixWidth>
        <MatrixHeight>$th</MatrixHeight>
      </TileMatrix>

EOL_TILEMATRIX;
      }
      $data = $data . "    </TileMatrixSet>\n";
    }
    // tail
    $data = $data . "  </Contents>\n</Capabilities>\n";
    // Shows the content
    TileCache::showContent($params, $data);
  }

  //
  // Process
  //
  function process($params) {
    switch( $params->getType() ) {
    case TileParams::T_TOROOT:
      // must redirect
      TileCache::showRootRedirection($params);
      return;
    case TileParams::T_ROOT:
      if( $params->needsRedirection() ) {
        TileCache::showRootRedirection($params);
      }
      else {
        $this->processRoot($params);
      }
      return;
    case TileParams::T_LAYER:
      TileCache::showForbidden($params);
      return;
    case TileParams::T_IMAGE:
      $layer = $this->getLayer($params);
      if( $layer === FALSE ) {
        TileCache::showNotFound($params);
      }
      else if( !$layer->hasItc($params->getImageTypeCode()) ) {
        TileCache::showNotFound($params);
      }
      else {
        $this->processImage($layer, $params);
      }
      return;
    case TileParams::T_KML:
      $layer = $this->getLayer($params);
      if( $layer === FALSE ) {
        TileCache::showNotFound($params);
      }
      else if( !$layer->hasItc($params->getImageTypeCode()) ) {
        TileCache::showNotFound($params);
      }
      else {
        $this->processKml($layer, $params);
      }
      return;
    case TileParams::T_KMLROOT:
      $layer = $this->getLayer($params);
      if( $layer === FALSE ) {
        TileCache::showNotFound($params);
      }
      else if( !$layer->hasItc($params->getImageTypeCode()) ) {
        TileCache::showNotFound($params);
      }
      else {
        $this->processKmlRoot($layer, $params);
      }
      return;
    case TileParams::T_TILEJSON:
      $layer = $this->getLayer($params);
      if( $layer === FALSE ) {
        TileCache::showNotFound($params);
      }
      else if( !$layer->hasItc($params->getImageTypeCode()) ) {
        TileCache::showNotFound($params);
      }
      else {
        $this->processTileJSON($layer, $params);
      }
      return;
    case TileParams::T_WMTS:
      $this->processWMTS($params);
      return;
    default:
      TileCache::showNotFound($params);
      return;
    }
  }
}


?>
