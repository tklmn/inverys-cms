<?php

namespace App\Services;

class BlockValidator
{
    protected array $errors = [];

    public function validate(?array $blocks): bool
    {
        $this->errors = [];

        if ($blocks === null) {
            return true;
        }

        if (! is_array($blocks)) {
            $this->errors[] = 'Blocks must be an array.';

            return false;
        }

        foreach ($blocks as $index => $block) {
            $this->validateBlock($block, "blocks[{$index}]");
        }

        return empty($this->errors);
    }

    protected function validateBlock(mixed $block, string $path): void
    {
        if (! is_array($block)) {
            $this->errors[] = "{$path}: Block must be an object.";

            return;
        }

        foreach (['data', 'settings', 'children'] as $key) {
            if (isset($block[$key]) && ! is_array($block[$key])) {
                $this->errors[] = "{$path}: {$key} must be an object or list.";
            }
        }

        if (empty($block['type'])) {
            $this->errors[] = "{$path}: Block type is required.";
        }

        if (empty($block['id'])) {
            $this->errors[] = "{$path}: Block id is required.";
        }

        if (isset($block['children']) && is_array($block['children'])) {
            foreach ($block['children'] as $index => $child) {
                $this->validateBlock($child, "{$path}.children[{$index}]");
            }
        }
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
