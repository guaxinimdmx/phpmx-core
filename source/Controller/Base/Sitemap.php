<?php

namespace Controller\Base;

use PhpMx\Response;

class Sitemap
{
    function default()
    {
        Response::type('xml');
        Response::content('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');
        Response::send();
    }
}
