<?php

use PhpMx\Dir;
use PhpMx\File;
use PhpMx\Path;
use PhpMx\Terminal;

return new class extends Terminal {

  function __invoke()
  {
    Dir::create('helper');
    Dir::create('helper/constant');
    Dir::create('helper/function');
    Dir::create('helper/script');
    Dir::create('middleware');
    Dir::create('migration');
    Dir::create('routes');
    Dir::create('source');
    Dir::create('storage');
    Dir::create('storage/assets');
    Dir::create('storage/certificate');
    Dir::create('terminal');

    if (!File::check('index.php'))
      File::copy(Path::seekFile('index.php'), 'index.php');

    Terminal::run('composer');
  }
};
