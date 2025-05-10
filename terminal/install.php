<?php

use PhpMx\Dir;
use PhpMx\Terminal;

return new class extends Terminal {

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

    Terminal::run('composer');
  }
};
