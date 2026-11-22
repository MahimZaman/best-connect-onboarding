<?php

declare(strict_types=1);

namespace BestConnectOnboarding\PDFs;

use Dskripchenko\PhpPdf\Pdf\Reader\PdfReference;
use Dskripchenko\PhpPdf\Pdf\Reader\ReaderDocument;
use Dskripchenko\PhpPdf\Pdf\Reader\PdfValueSerializer;
use Dskripchenko\PhpPdf\Pdf\Writer;

defined('ABSPATH') || exit;

final class Normalizer
{
    /**
     * Normalize a PDF into a classic-xref PDF that
     * plusforta/pdf-forms can read.
     *
     * @param string $source Absolute source PDF path.
     * @return string Absolute normalized PDF path.
     */
    public static function normalize(string $source): string
    {
        if (
            $source === '' ||
            !is_file($source) ||
            !is_readable($source)
        ) {
            throw new \RuntimeException(
                'PDF source file is not readable.'
            );
        }

        $bytes = file_get_contents($source);

        if (
            $bytes === false ||
            $bytes === ''
        ) {
            throw new \RuntimeException(
                'Unable to read PDF source.'
            );
        }

        /*
         * Already compatible PDFs do not need to be
         * rewritten.
         */
        if (!self::uses_modern_xref($bytes)) {
            return $source;
        }

        /*
         * dskripchenko reader supports:
         *
         * - classic xref
         * - xref streams
         * - object streams
         * - recovery
         */
        $document =
            ReaderDocument::fromBytes(
                $bytes
            );

        /*
         * The installed reader decrypts objects when
         * a password is supplied. We cannot safely
         * rewrite an encrypted document without
         * preserving its encryption configuration.
         */
        $trailer =
            $document->trailer();

        if (
            $trailer->get('Encrypt') !== null
        ) {
            throw new \RuntimeException(
                'Encrypted PDF contracts are not supported for normalization.'
            );
        }

        /*
         * Extract every actual object from the xref.
         *
         * XrefTable keeps:
         *
         * - offsets for normal objects
         * - compressed locations for object-stream objects
         *
         * Both are resolved through ReaderDocument::getObject().
         */
        $xref =
            self::get_private_property(
                $document,
                'xref'
            );

        $offsets =
            self::get_private_property(
                $xref,
                'offsets'
            );

        $compressed =
            self::get_private_property(
                $xref,
                'compressed'
            );

        if (
            !is_array($offsets)
        ) {
            $offsets = [];
        }

        if (
            !is_array($compressed)
        ) {
            $compressed = [];
        }

        $object_numbers =
            array_unique(
                array_merge(
                    array_keys($offsets),
                    array_keys($compressed)
                )
            );

        sort(
            $object_numbers,
            SORT_NUMERIC
        );

        if (
            empty($object_numbers)
        ) {
            throw new \RuntimeException(
                'No PDF objects were found.'
            );
        }

        /*
         * The original object number becomes the local
         * object identifier. Writer receives a new object
         * number for every object.
         */
        $writer =
            new Writer(
                version: '1.7',
                useXrefStream: false,
                useObjectStreams: false
            );

        $map = [];

        foreach (
            $object_numbers as $object_number
        ) {

            $object_number =
                (int) $object_number;

            /*
             * Object 0 is the free object and must not
             * be copied as a normal PDF object.
             */
            if (
                $object_number === 0
            ) {
                continue;
            }

            $map[$object_number] =
                $writer->reserveObject();
        }

        if (
            empty($map)
        ) {
            throw new \RuntimeException(
                'Unable to allocate normalized PDF objects.'
            );
        }

        /*
         * Remap every indirect reference from the original
         * object number to the new Writer object number.
         */
        $serializer =
            new PdfValueSerializer(
                static function (
                    int $object_number
                ) use (&$map): int {

                    if (
                        isset(
                            $map[$object_number]
                        )
                    ) {
                        return $map[$object_number];
                    }

                    /*
                     * References to object 0 are free.
                     */
                    if (
                        $object_number === 0
                    ) {
                        return 0;
                    }

                    throw new \RuntimeException(
                        'PDF contains an unresolved object reference: ' .
                            $object_number
                    );
                }
            );

        /*
         * Resolve and serialize every object.
         */
        foreach (
            array_keys($map) as $object_number
        ) {

            $value =
                $document->getObject(
                    (int) $object_number
                );

            if (
                $value === null
            ) {
                throw new \RuntimeException(
                    'Unable to resolve PDF object ' .
                        $object_number
                );
            }

            $writer->setObject(
                $map[$object_number],
                $serializer->encode(
                    $value
                )
            );
        }

        /*
         * Resolve original catalog/root.
         */
        $root_reference =
            $trailer->get('Root');

        if (
            !$root_reference instanceof PdfReference
        ) {
            throw new \RuntimeException(
                'PDF catalog root is missing.'
            );
        }

        $root_number =
            $root_reference->number;

        if (
            !isset(
                $map[$root_number]
            )
        ) {
            throw new \RuntimeException(
                'PDF catalog root could not be mapped.'
            );
        }

        $writer->setRoot(
            $map[$root_number]
        );

        /*
         * Preserve the document Info dictionary when
         * one exists.
         */
        $info_reference =
            $trailer->get('Info');

        if (
            $info_reference instanceof PdfReference &&
            isset(
                $map[$info_reference->number]
            )
        ) {
            $writer->setInfo(
                $map[$info_reference->number]
            );
        }

        /*
         * Generate classic-xref PDF.
         */
        $normalized =
            $writer->toBytes();

        if (
            $normalized === ''
        ) {
            throw new \RuntimeException(
                'Unable to generate normalized PDF.'
            );
        }

        /*
         * Verify the generated PDF with the modern
         * reader first.
         */
        ReaderDocument::fromBytes(
            $normalized
        );

        /*
         * Then verify that PlusForta can actually
         * open the normalized file.
         */
        $destination =
            self::create_destination(
                $source
            );

        if (
            file_put_contents(
                $destination,
                $normalized
            ) === false
        ) {
            throw new \RuntimeException(
                'Unable to save normalized PDF.'
            );
        }

        return $destination;
    }

    /**
     * Normalize a WordPress PDF attachment and create
     * a new attachment for the normalized file.
     */
    public static function normalize_attachment(
        int $attachment_id
    ): int {

        if (
            !$attachment_id
        ) {
            throw new \RuntimeException(
                'Invalid PDF attachment.'
            );
        }

        if (
            get_post_type(
                $attachment_id
            ) !== 'attachment'
        ) {
            throw new \RuntimeException(
                'Selected item is not an attachment.'
            );
        }

        if (
            get_post_mime_type(
                $attachment_id
            ) !== 'application/pdf'
        ) {
            throw new \RuntimeException(
                'Selected attachment is not a PDF.'
            );
        }

        /*
         * Reuse an already-normalized attachment.
         */
        $existing =
            absint(
                get_post_meta(
                    $attachment_id,
                    '_bcob_normalized_pdf_id',
                    true
                )
            );

        if (
            $existing &&
            get_post_type(
                $existing
            ) === 'attachment' &&
            get_post_mime_type(
                $existing
            ) === 'application/pdf'
        ) {
            return $existing;
        }

        $source =
            get_attached_file(
                $attachment_id
            );

        if (
            !$source ||
            !is_string($source)
        ) {
            throw new \RuntimeException(
                'Unable to locate PDF attachment.'
            );
        }

        $normalized =
            self::normalize(
                $source
            );

        /*
         * If no conversion was required, use the
         * original attachment.
         */
        if (
            $normalized === $source
        ) {
            return $attachment_id;
        }

        $upload_dir =
            wp_upload_dir();

        $filename =
            wp_unique_filename(
                $upload_dir['path'],
                wp_basename(
                    $normalized
                )
            );

        $destination =
            trailingslashit(
                $upload_dir['path']
            ) .
            $filename;

        if (
            !copy(
                $normalized,
                $destination
            )
        ) {
            throw new \RuntimeException(
                'Unable to copy normalized PDF.'
            );
        }

        /*
         * Create the WordPress attachment.
         */
        $original_title =
            get_the_title(
                $attachment_id
            );

        $new_attachment = [
            'post_mime_type' =>
            'application/pdf',

            'post_title' =>
            $original_title,

            'post_content' =>
            '',

            'post_status' =>
            'inherit',

            'post_parent' =>
            wp_get_post_parent_id(
                $attachment_id
            ),
        ];

        $new_id =
            wp_insert_attachment(
                $new_attachment,
                $destination,
                $new_attachment['post_parent']
            );

        if (
            is_wp_error($new_id)
        ) {
            @unlink(
                $destination
            );

            throw new \RuntimeException(
                $new_id->get_error_message()
            );
        }

        require_once ABSPATH .
            'wp-admin/includes/image.php';

        $metadata =
            wp_generate_attachment_metadata(
                $new_id,
                $destination
            );

        if (
            !empty($metadata)
        ) {
            wp_update_attachment_metadata(
                $new_id,
                $metadata
            );
        }

        update_post_meta(
            $new_id,
            '_bcob_normalized_from',
            $attachment_id
        );

        update_post_meta(
            $attachment_id,
            '_bcob_normalized_pdf_id',
            $new_id
        );

        return (int) $new_id;
    }

    /**
     * Detect an actual xref stream.
     */
    private static function uses_modern_xref(
        string $bytes
    ): bool {

        return preg_match(
            '/\/Type\s*\/XRef\b/',
            $bytes
        ) === 1;
    }

    /**
     * Read a private property from one of the
     * installed dskripchenko reader objects.
     */
    private static function get_private_property(
        object $object,
        string $property
    ): mixed {

        $reflection =
            new \ReflectionProperty(
                $object,
                $property
            );

        return $reflection->getValue(
            $object
        );
    }

    /**
     * Create a temporary normalized destination.
     */
    private static function create_destination(
        string $source
    ): string {

        $directory =
            dirname(
                $source
            );

        $filename =
            pathinfo(
                $source,
                PATHINFO_FILENAME
            );

        return
            $directory .
            DIRECTORY_SEPARATOR .
            $filename .
            '-normalized-' .
            wp_generate_uuid4() .
            '.pdf';
    }
}
