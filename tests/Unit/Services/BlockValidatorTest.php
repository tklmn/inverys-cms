<?php

namespace Tests\Unit\Services;

use App\Services\BlockValidator;
use PHPUnit\Framework\TestCase;

class BlockValidatorTest extends TestCase
{
    private BlockValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BlockValidator;
    }

    public function test_null_blocks_are_valid(): void
    {
        $this->assertTrue($this->validator->validate(null));
    }

    public function test_empty_array_is_valid(): void
    {
        $this->assertTrue($this->validator->validate([]));
    }

    public function test_valid_block(): void
    {
        $blocks = [
            ['id' => 'b1', 'type' => 'text', 'data' => ['content' => 'Hello']],
        ];
        $this->assertTrue($this->validator->validate($blocks));
    }

    public function test_block_without_type_is_invalid(): void
    {
        $blocks = [
            ['id' => 'b1', 'data' => ['content' => 'Hello']],
        ];
        $this->assertFalse($this->validator->validate($blocks));
        $this->assertNotEmpty($this->validator->errors());
    }

    public function test_block_without_id_is_invalid(): void
    {
        $blocks = [
            ['type' => 'text', 'data' => ['content' => 'Hello']],
        ];
        $this->assertFalse($this->validator->validate($blocks));
    }

    public function test_nested_children_are_validated(): void
    {
        $blocks = [
            [
                'id' => 'b1',
                'type' => 'section',
                'children' => [
                    ['type' => 'text'], // Missing id
                ],
            ],
        ];
        $this->assertFalse($this->validator->validate($blocks));
        $this->assertStringContainsString('children[0]', $this->validator->errors()[0]);
    }

    public function test_valid_nested_blocks(): void
    {
        $blocks = [
            [
                'id' => 'b1',
                'type' => 'section',
                'children' => [
                    ['id' => 'b2', 'type' => 'text', 'data' => []],
                ],
            ],
        ];
        $this->assertTrue($this->validator->validate($blocks));
    }
}
