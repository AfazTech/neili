<?php

declare(strict_types=1);

namespace Neili\Tests\Unit;

use Neili\KeyboardBuilder;
use PHPUnit\Framework\TestCase;

final class KeyboardBuilderTest extends TestCase
{
    public function testReplyRowBuildsSimpleButtons(): void
    {
        $keyboard = (new KeyboardBuilder())
            ->row('Yes', 'No')
            ->row('Maybe')
            ->build();

        self::assertSame([
            'keyboard' => [
                [['text' => 'Yes'], ['text' => 'No']],
                [['text' => 'Maybe']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ], $keyboard);
    }

    public function testReplyRowWithAllOptions(): void
    {
        $keyboard = (new KeyboardBuilder())
            ->row('A')
            ->resize(false)
            ->oneTime(true)
            ->persistent()
            ->inputFieldPlaceholder('Type here')
            ->selective()
            ->build();

        self::assertFalse($keyboard['resize_keyboard']);
        self::assertTrue($keyboard['one_time_keyboard']);
        self::assertTrue($keyboard['is_persistent']);
        self::assertSame('Type here', $keyboard['input_field_placeholder']);
        self::assertTrue($keyboard['selective']);
    }

    public function testCustomReplyRow(): void
    {
        $keyboard = (new KeyboardBuilder())
            ->replyRow([
                ['text' => 'Share', 'request_users' => ['request_id' => 1]],
                ['text' => 'Location', 'request_location' => true],
            ])
            ->build();

        self::assertSame(
            ['request_id' => 1],
            $keyboard['keyboard'][0][0]['request_users']
        );
        self::assertTrue($keyboard['keyboard'][0][1]['request_location']);
    }

    public function testInlineRowBuildsCallbackButtons(): void
    {
        $keyboard = (new KeyboardBuilder())
            ->inlineRow(['Click me' => 'cb_1'])
            ->build();

        self::assertSame([
            'inline_keyboard' => [
                [['text' => 'Click me', 'callback_data' => 'cb_1']],
            ],
        ], $keyboard);
    }

    public function testInlineUrlRow(): void
    {
        $keyboard = (new KeyboardBuilder())
            ->inlineUrlRow(['Open' => 'https://example.com'])
            ->build();

        self::assertSame([
            'inline_keyboard' => [
                [['text' => 'Open', 'url' => 'https://example.com']],
            ],
        ], $keyboard);
    }

    public function testInlineButtonRowWithCustomButton(): void
    {
        $keyboard = (new KeyboardBuilder())
            ->inlineButtonRow([
                ['text' => 'Web App', 'web_app' => ['url' => 'https://example.com']],
            ])
            ->build();

        self::assertSame(
            ['url' => 'https://example.com'],
            $keyboard['inline_keyboard'][0][0]['web_app']
        );
    }

    public function testInlineButtonSingle(): void
    {
        $keyboard = (new KeyboardBuilder())
            ->inlineButton(['text' => 'Copy', 'copy_text' => ['text' => 'abc']])
            ->build();

        self::assertSame('abc', $keyboard['inline_keyboard'][0][0]['copy_text']['text']);
    }

    public function testForceReplyOnInlineKeyboard(): void
    {
        $keyboard = (new KeyboardBuilder())
            ->inlineRow(['a' => 'b'])
            ->forceReply()
            ->build();

        self::assertTrue($keyboard['force_reply']);
    }

    public function testForceReplyOnReplyKeyboard(): void
    {
        $keyboard = (new KeyboardBuilder())
            ->row('A')
            ->forceReply()
            ->build();

        self::assertTrue($keyboard['force_reply']);
    }

    public function testForceReplyObjectHelper(): void
    {
        self::assertSame([
            'force_reply' => true,
            'input_field_placeholder' => 'Ask me',
        ], KeyboardBuilder::forceReplyObject('Ask me'));
    }

    public function testRemoveKeyboardObjectHelper(): void
    {
        self::assertSame(
            ['remove_keyboard' => true],
            KeyboardBuilder::removeKeyboard()
        );
    }

    public function testRemoveKeyboardWithSelective(): void
    {
        self::assertSame(
            ['remove_keyboard' => true, 'selective' => true],
            KeyboardBuilder::removeKeyboard(true)
        );
    }

    public function testMixedRowsThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot mix reply rows with an inline keyboard');

        (new KeyboardBuilder())
            ->inlineRow(['a' => 'b'])
            ->row('text');
    }

    public function testClearResetsAllState(): void
    {
        $builder = (new KeyboardBuilder())
            ->row('a')
            ->resize(false)
            ->oneTime(true)
            ->persistent()
            ->clear();

        self::assertSame([
            'keyboard' => [],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ], $builder->build());
    }
}
