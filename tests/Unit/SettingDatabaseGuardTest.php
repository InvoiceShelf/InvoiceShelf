<?php

use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;

it('returns null from getSetting when the connection resolver is not bound', function () {
    $resolver = Model::getConnectionResolver();

    try {
        Model::unsetConnectionResolver();

        expect(Setting::getSetting('version'))->toBeNull();
    } finally {
        Model::setConnectionResolver($resolver);
    }
});
