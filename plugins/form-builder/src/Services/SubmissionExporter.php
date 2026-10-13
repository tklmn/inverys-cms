<?php

namespace Plugins\FormBuilder\Services;

use Illuminate\Support\Collection;
use Plugins\FormBuilder\Models\Form;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionExporter
{
    /**
     * Characters that trigger formula execution in spreadsheet applications.
     */
    private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r", "\n"];

    /**
     * Sanitize a value to prevent CSV formula injection.
     */
    private static function sanitizeCsvValue(string $value): string
    {
        $trimmed = ltrim($value);
        if ($trimmed !== '' && in_array($trimmed[0], self::FORMULA_TRIGGERS, true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Export submissions as a CSV download.
     */
    public static function export(Form $form, Collection $submissions): StreamedResponse
    {
        $fields = $form->getDataFields();
        $headers = [];
        $fieldNames = [];

        foreach ($fields as $field) {
            $name = $field['name'] ?? '';
            $label = $field['label'] ?? $name;
            if (! empty($name)) {
                $headers[] = $label;
                $fieldNames[] = $name;
            }
        }

        // Add metadata columns
        $headers[] = 'Submitted At';
        $headers[] = 'IP Address';

        $filename = 'submissions-'.$form->slug.'-'.now()->format('Y-m-d').'.csv';

        return new StreamedResponse(function () use ($headers, $fieldNames, $submissions) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel UTF-8 compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, $headers);

            // Data rows
            foreach ($submissions as $submission) {
                $row = [];
                $data = $submission->data ?? [];

                foreach ($fieldNames as $name) {
                    $value = $data[$name] ?? '';
                    $row[] = self::sanitizeCsvValue(is_array($value) ? implode(', ', $value) : $value);
                }

                $row[] = $submission->created_at?->format('Y-m-d H:i:s') ?? '';
                $row[] = $submission->ip_address ?? '';

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 200, [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
