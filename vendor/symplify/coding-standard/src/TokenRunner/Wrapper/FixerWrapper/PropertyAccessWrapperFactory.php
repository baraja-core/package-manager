<?php

declare(strict_types=1);

namespace Symplify\CodingStandard\TokenRunner\Wrapper\FixerWrapper;

use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use Symplify\CodingStandard\TokenRunner\Guard\TokenTypeGuard;
use Symplify\CodingStandard\TokenRunner\ValueObject\Wrapper\FixerWrapper\PropertyAccessWrapper;

final class PropertyAccessWrapperFactory
{
    /**
     * @var TokenTypeGuard
     */
    private $tokenTypeGuard;

    public function __construct(TokenTypeGuard $tokenTypeGuard)
    {
        $this->tokenTypeGuard = $tokenTypeGuard;
    }

    public function createFromTokensAndPosition(Tokens $tokens, int $position): PropertyAccessWrapper
    {
        /** @var Token $token */
        $token = $tokens[$position];
        $this->tokenTypeGuard->ensureIsTokenType($token, [T_VARIABLE], __METHOD__);

        return new PropertyAccessWrapper($tokens, $position);
    }
}
