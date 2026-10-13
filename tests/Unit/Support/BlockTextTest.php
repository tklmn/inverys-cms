<?php

namespace Tests\Unit\Support;

use App\Support\BlockText;
use PHPUnit\Framework\TestCase;

class BlockTextTest extends TestCase
{
    public function test_it_collects_readable_text_from_nested_blocks(): void
    {
        $blocks = [
            ['id' => 'h', 'type' => 'hero', 'data' => ['title' => 'Welcome', 'text' => 'Glad you are here', 'primary_url' => '/contact', 'image' => 'media/x.jpg']],
            ['id' => 's', 'type' => 'section', 'data' => [], 'children' => [
                ['id' => 't', 'type' => 'text', 'data' => ['content' => '<p>First&nbsp;paragraph</p><p>Second <strong>paragraph</strong></p>']],
                ['id' => 'p', 'type' => 'pricing', 'data' => ['plans' => [['name' => 'Basic', 'button_url' => '#', 'featured' => true]]]],
            ]],
        ];

        $text = BlockText::extract($blocks);

        $this->assertStringContainsString('Welcome', $text);
        $this->assertStringContainsString('Glad you are here', $text);
        $this->assertStringContainsString('First paragraph Second paragraph', str_replace("\u{00A0}", ' ', $text));
        $this->assertStringContainsString('Basic', $text);
        // URLs and images are not readable text
        $this->assertStringNotContainsString('/contact', $text);
        $this->assertStringNotContainsString('media/x.jpg', $text);
    }

    public function test_video_transcript_is_searchable_but_caption_files_and_targets_are_not(): void
    {
        $text = BlockText::extract([
            ['id' => 'v', 'type' => 'video', 'data' => ['url' => 'clip.mp4', 'title' => 'Tour', 'captions' => 'media/clip.vtt', 'transcript' => 'Spoken words', 'transcript_url' => '/transcript']],
            ['id' => 'p', 'type' => 'pricing', 'data' => ['plans' => [['name' => 'Pro', 'button_target' => '_blank']]]],
        ]);

        $this->assertStringContainsString('Tour', $text);
        $this->assertStringContainsString('Spoken words', $text);
        $this->assertStringNotContainsString('clip.vtt', $text);
        $this->assertStringNotContainsString('/transcript', $text);
        $this->assertStringNotContainsString('_blank', $text);
    }

    public function test_empty_input_gives_empty_text(): void
    {
        $this->assertSame('', BlockText::extract(null));
        $this->assertSame('', BlockText::extract([]));
    }
}
