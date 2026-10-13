<?php

namespace App\Http\Controllers;

use App\Support\SecurityTxt;
use Illuminate\Http\Response;

/**
 * /.well-known/security.txt per RFC 9116. Content and rules: App\Support\SecurityTxt
 * (Settings → Security, otherwise SECURITY_TXT_* from the .env). Without a contact: 404.
 */
class SecurityTxtController extends Controller
{
    public function __invoke(): Response
    {
        $content = SecurityTxt::build();
        abort_if($content === null, 404);

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
