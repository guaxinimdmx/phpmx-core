<?php

use PhpMx\Dir;

return new class extends \PhpMx\Terminal {

  function __invoke()
  {
    Dir::create('helper');
    Dir::create('helper/constant');
    Dir::create('helper/function');
    Dir::create('helper/script');
    Dir::create('routes');
    Dir::create('source');
    Dir::create('storage');
    Dir::create('storage/assets');
    Dir::create('storage/certificate');
    Dir::create('terminal');
  }
};
