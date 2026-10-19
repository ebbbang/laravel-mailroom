<?php

namespace Ebbbang\Mailroom\Tests\Unit;

use Ebbbang\Mailroom\Support\Avatar;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AvatarTest extends TestCase
{
    #[Test]
    public function initials_come_from_the_first_two_words_of_the_name(): void
    {
        $this->assertSame('RO', Avatar::for('rachel@example.test', 'Rachel Okonkwo')->initials);
        $this->assertSame('JV', Avatar::for('j@example.test', 'Jan van der Berg')->initials);
        $this->assertSame('Ü', Avatar::for('u@example.test', 'ülrich')->initials);
    }

    #[Test]
    public function without_a_name_they_come_from_the_address(): void
    {
        $this->assertSame('RO', Avatar::for('rachel.okonkwo@example.test')->initials);
        $this->assertSame('S', Avatar::for('sam@example.test')->initials);
    }

    #[Test]
    public function with_nothing_to_go_on_it_is_a_question_mark(): void
    {
        $this->assertSame('?', Avatar::for(null)->initials);
        $this->assertSame('?', Avatar::for('')->initials);
    }

    #[Test]
    public function the_tone_follows_the_address_ignoring_case_and_name(): void
    {
        $tone = Avatar::for('rachel@example.test', 'Rachel')->tone;

        $this->assertSame($tone, Avatar::for('Rachel@Example.TEST', 'R. Okonkwo')->tone);
        $this->assertGreaterThanOrEqual(0, $tone);
        $this->assertLessThan(Avatar::TONES, $tone);
    }
}
