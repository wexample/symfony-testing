<?php

namespace Wexample\SymfonyTesting\Traits;

use App\Entity\User;
use JetBrains\PhpStorm\Pure;
use Wexample\SymfonyHelpers\Helper\ClassHelper;

trait TextManipulationTestCaseTrait
{
    #[Pure]
    public function buildEmailAddress(User $user): string
    {
        return 'test.'.$user->getUsername().'@domain.com';
    }

    #[Pure]
    public function loremIpsumUnsafe($length = null): string
    {
        return substr(
            'Lorem ipsum dolor sit amet. '.
            $this->fuzzerString().'</script>',
            0,
            $length
        );
    }

    public function fuzzerString(): string
    {
        return '\'"\`<\r\n <b>Bold</b> <i>Italic</i>. éàù@% !!! <script> alert("JS injection test !");';
    }

    public function buildUniqueTitle(object|string $entity): string
    {
        return
            substr(strtoupper(
                ClassHelper::getTableizedName($entity)
            ),
                0,
                5
            ).' '.uniqid();
    }
}
