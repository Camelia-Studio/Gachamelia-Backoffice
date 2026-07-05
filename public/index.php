<?php

use App\Kernel;
use App\Http\PublicPathRequestNormalizer;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context, Request $request) {
    PublicPathRequestNormalizer::normalize($request, $context['APP_BASE_PATH'] ?? '');

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
