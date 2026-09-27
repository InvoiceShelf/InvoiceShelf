<?php

namespace App\Support\Media;

use App\Platform\Persistence\ModelIdentityMap;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Decides where an upload sits on whichever disk the media library is writing
 * to.
 *
 * Attachments of the three document kinds are pooled, one folder per kind;
 * anything else is given a folder to itself, named after the media row's own
 * id. Generated conversions and responsive variants go in a sub-folder of
 * whichever folder the original landed in.
 *
 * These layouts are an on-disk contract rather than a preference. Installs
 * already have files sitting at exactly these paths, and nothing rewrites them,
 * so renaming a folder here loses whatever is stored below it. That is why the
 * conversions sub-folder is still spelled "conversations": it is a typo, it has
 * always been the typo, and correcting it would strand every conversion ever
 * written.
 */
class CustomPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->folder($media);
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->folder($media, 'conversations');
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->folder($media, 'responsive-images');
    }

    /**
     * The folder shared by everything belonging to the given media.
     *
     * The fallback hands back the row's key as it comes, an integer, and lets
     * the declared return type do the widening. A media row without a key
     * therefore fails here rather than filing itself under an empty name,
     * which is the behaviour that has always been in place.
     */
    protected function getBasePath(Media $media): string
    {
        return match ($media->model_type) {
            ModelIdentityMap::INVOICE_ALIAS => 'Invoices',
            ModelIdentityMap::ESTIMATE_ALIAS => 'Estimates',
            ModelIdentityMap::QUOTE_ALIAS => 'Quotes',
            ModelIdentityMap::PAYMENT_ALIAS => 'Payments',
            default => $media->getKey(),
        };
    }

    /**
     * That folder, or one named sub-folder of it, closed off with the
     * separator the media library expects to find at the end.
     */
    private function folder(Media $media, ?string $subFolder = null): string
    {
        $path = $this->getBasePath($media).'/';

        return $subFolder === null ? $path : $path.$subFolder.'/';
    }
}
