<?php

namespace App\Support;

class InputSanitizer
{
    private const TEXT_FIELDS = [
        'site_name', 'symbol', 'name', 'description', 'purpose', 'remarks', 'notes', 'message', 'title',
        'reason', 'action', 'module', 'change_type', 'type', 'unit', 'source', 'destination', 'funding_source', 'reference', 'responsible_center',
        'ris_no', 'sku', 'category', 'category_name', 'department', 'search', 'q', 'field_id',
        'po_number', 'pr_number', 'requested_by', 'requested_by_title', 'issued_by',
        'issued_by_title', 'noted_by', 'noted_by_title', 'received_by', 'received_by_title',
    ];

    public static function clean(string $key, mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $field = last(explode('.', $key));
        if ($field === 'email') {
            return mb_strtolower(trim($value));
        }

        if (! in_array($field, self::TEXT_FIELDS, true)) {
            return $value;
        }

        // These fields store plain text. Keep Unicode, quotes, tabs and line breaks.
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', strip_tags($value));
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
