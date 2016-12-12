# tilecache_php

## How to use

### Basis

Construct TileCache instance and call process() like below:

```php
$layer1 = new Layer(...);
...
$tilecache = new TileCache('tilecache', array($layer1, $layer2, ...));
$url = new Url($_SERVER);
$params = new TileParams($url);
$tilecache->process($params);
```

We needs array of Layer instance before we construct the TileCache.
Each name of layer instance (specified by an argument of constructor) must be unique.

### Layer

Layer contains name, profile, acceptable image types, extent, z-extent, margin, source, store, checker and options.

```php
  new Layer(
    'pntms-merc', // NAME
    $prof_merc, // Profile instance
    array(ITCode::IT_PNG, ITCode::IT_JPEG), // Acceptable image types
    new Box(122,22,149,46), // Extent (lonlat)
    new ZExtent(0,16), // Extent of zoom (z)
    new XY(256,128), // Margin (pixel)
    $src_mswms,  // Source
    $store_pg,  // Store
    $checker_pn, // Cheker
    array(
      'blank' => array(ITCode::IT_PNG=>'eee.png',ITCode::IT_JPEG=>'eee.jpg'), // substitute when not found.
      'ob'    => array(ITCode::IT_PNG=>'eee.png',ITCode::IT_JPEG=>'eee.jpg'), // substitute when out of bounds.
      'layers' => array(  // Options for MSWMSSource or WMSSource
        new WmsLayer('AzaName','mediumborder'),
        new WmsLayer('MncplName','mediumborder'),
        new WmsLayer('PrefName','largeborder')
      )
    )
  )
```

### layer options
<dl>
<dt>blank</dt>
<dd>Hash whose key is "Image Type Code" (defined in ITCode) and value is path to substitude image. This is used when requested Z,X,Y is within bounds (by BOX and ZExtent) but cannot get the image.</dd>
<dt>ob</dt>
<dd>Hash whose key is "Image Type Code" (defined in ITCode) and value is path to substitude image. This is used when requested Z,X,Y is out of bounds (by Box and ZExtent).</dd>
</dl>

Other options are used by source, store or checker.

### Profile

Porfile provides projection (prj(), inv()) and tile index calculation.

### Source

Source provides image. Currently, supports MSWMSSource (MapServer executable with WMS parameters), WMSSource (Remote WMS) and TMSSource (local file system).

MSWMSSource and WMSSource uses 'layers' (WMS layers) option in Layer instance.

### Store

Store provides storage for fetched images. Currently supports PGStore (with PostgreSQL) and FileStore.

### Checker

This is used to reduce stored images. TileCache instance tells the checker whether there is any feature within the extent made by requested tile and margin.

If the checker decides thre is no feature within the extent, TileCache instance returns the substitude image pointed by 'blank' option.
