<?php

namespace Ebbbang\TestMail\Support;

use Ebbbang\TestMail\Models\TestMailMessage;

/**
 * Rewrites cid: references in an HTML body to data: URIs.
 *
 * Embedded images (from $message->embed()) live as separate MIME parts and
 * are referenced as <img src="cid:...">. Without this the preview and the
 * exported .html would both show broken images.
 */
class CidInliner
{
    public function inline(TestMailMessage $message, ?string $html): ?string
    {
        if (blank($html)) {
            return $html;
        }

        $replacements = [];

        foreach ($message->inlineAttachments() as $attachment) {
            $dataUri = $attachment->toDataUri();

            if ($dataUri === null) {
                continue;
            }

            /*
             * Keyed on the content id *and* the filename, because the two do
             * not always agree in the stored body.
             *
             * Symfony's Email::prepareParts() rewrites cid: references to the
             * generated content id on a local copy of the HTML used to build
             * the MIME, and never writes that back to the Email. So the body
             * we record can still point at "cid:logo.png" while the part
             * reports a generated content id -- and which of the two you get
             * varies between Symfony Mime 7.x and 8.x. Matching both means
             * embedded images resolve either way.
             */
            foreach ([$attachment->content_id, $attachment->filename] as $reference) {
                if (filled($reference)) {
                    $replacements['cid:'.$reference] = $dataUri;
                }
            }
        }

        return $replacements === [] ? $html : strtr($html, $replacements);
    }
}
