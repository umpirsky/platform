<?php

namespace Oro\Bundle\AddressBundle\Validator\Constraints;

use Oro\Bundle\AddressBundle\Entity\PrimaryItem;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ContainsPrimaryValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!is_array($value) && !($value instanceof \Traversable && $value instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($value, 'array or Traversable and ArrayAccess');
        }

        $primaryItemsNumber = 0;
        $totalItemsNumber = 0;
        /** @var PrimaryItem $item */
        foreach ($value as $item) {
            if ($item instanceof PrimaryItem && $item->isPrimary()) {
                $primaryItemsNumber++;
            }
            $totalItemsNumber++;
        }

        if ($totalItemsNumber > 0 && $primaryItemsNumber != 1) {
            $this->context->addViolation($constraint->message);
        }
    }
}
