<?php

namespace App\Platform\Mail\Application;

use App\Platform\Mail\Contracts\EmailLogWriter;
use App\Platform\Mail\Models\EmailLog;
use App\Platform\Persistence\ModelIdentityMap;
use App\Support\PublicToken;
use Illuminate\Database\Eloquent\Model;

class EloquentEmailLogWriter implements EmailLogWriter
{
    public function record(Model $mailable, array $message): string
    {
        $log = EmailLog::create([
            ...$message,
            'mailable_type' => ModelIdentityMap::aliasFor($mailable::class),
            'mailable_id' => $mailable->getKey(),
        ]);

        $log->token = PublicToken::make();
        $log->save();

        return $log->token;
    }
}
