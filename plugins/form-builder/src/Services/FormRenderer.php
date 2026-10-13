<?php

namespace Plugins\FormBuilder\Services;

use Illuminate\Support\Facades\View;
use Plugins\FormBuilder\Models\Form;

class FormRenderer
{
    /**
     * Render a complete form HTML on the frontend.
     */
    public static function render(Form $form): string
    {
        $fields = $form->fields ?? [];
        $fieldsHtml = static::renderItems($fields);

        return View::make('form-builder::form', [
            'form' => $form,
            'fieldsHtml' => $fieldsHtml,
        ])->render();
    }

    /**
     * Render a list of items (fields, rows, columns) recursively.
     */
    protected static function renderItems(array $items): string
    {
        $html = '';

        foreach ($items as $item) {
            $type = $item['type'] ?? 'text';

            if ($type === 'row') {
                $html .= static::renderRow($item);
            } elseif ($type === 'column') {
                // Columns are rendered inside rows
                continue;
            } else {
                $width = $item['width'] ?? 'full';
                $widthClass = $width === 'half' ? 'md:col-span-1' : 'md:col-span-2';

                $html .= '<div class="'.$widthClass.'">';
                $html .= static::renderField($item);
                $html .= '</div>';
            }
        }

        return $html;
    }

    /**
     * Render a row container with columns.
     */
    protected static function renderRow(array $row): string
    {
        $children = $row['children'] ?? [];
        $colCount = count($children);
        if ($colCount < 1) {
            return '';
        }

        $gridCols = [1 => '1', 2 => '2', 3 => '3', 4 => '4'];
        $gridClass = 'md:grid-cols-'.($gridCols[$colCount] ?? $colCount);

        $html = '<div class="md:col-span-2 grid grid-cols-1 '.$gridClass.' gap-4">';

        foreach ($children as $column) {
            $html .= '<div>';
            foreach ($column['children'] ?? [] as $field) {
                $html .= '<div class="mb-4 last:mb-0">';
                $html .= static::renderField($field);
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a single field.
     */
    protected static function renderField(array $field): string
    {
        $type = $field['type'] ?? 'text';

        $viewName = "form-builder::fields.{$type}";
        if (! View::exists($viewName)) {
            $viewName = 'form-builder::fields.text';
        }

        return View::make($viewName, ['field' => $field])->render();
    }
}
