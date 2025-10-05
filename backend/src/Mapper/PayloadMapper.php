<?php

namespace App\Mapper;

use ReflectionClass;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Verantwoordelijk voor het mappen van payload arrays naar DTO-objecten.
 */
class PayloadMapper
{
    /**
     * Zet een associatieve array om naar een DTO-object.
     *
     * - Werkt met publieke properties of setters
     * - Valideert op onbekende of ongeldige keys indien $strict = true
     *
     * @template T of object
     * @param array $payload De JSON-decoded invoer als associatieve array
     * @param T $dto Doelobject waar de data op gezet wordt
     * @param bool $strict Indien true, geeft foutmelding bij onbekende of foute veldnamen
     * @return T Het gevulde DTO-object
     */
    public function map(array $payload, object $dto, bool $strict = false): object
    {
        $dtoReflection = $this->createReflection($dto);
        $allowedProperties = $this->extractPropertyNames($dtoReflection);

        $this->validateKeys(array_keys($payload), $allowedProperties, $strict);

        $accessor = PropertyAccess::createPropertyAccessor();
        foreach ($payload as $key => $value) {
            if (in_array($key, $allowedProperties, true)) {
                $accessor->setValue($dto, $key, $value);
            }
        }

        return $dto;
    }

    /**
     * Reflecteert op het DTO-object voor inspectie van properties.
     *
     * @param object $dto Het te inspecteren DTO-object
     * @return ReflectionClass
     */
    private function createReflection(object $dto): ReflectionClass
    {
        try {
            return new ReflectionClass($dto);
        } catch (\ReflectionException $e) {
            throw new \RuntimeException('Kon DTO niet reflecteren: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Haalt alle property-namen van het DTO-object op.
     *
     * @param ReflectionClass $reflection De DTO-reflection
     * @return string[] De lijst van toegestane veldnamen
     */
    private function extractPropertyNames(ReflectionClass $reflection): array
    {
        return array_map(fn($prop) => $prop->getName(), $reflection->getProperties());
    }

    /**
     * Valideert payload keys tegen de DTO-properties.
     *
     * - Veldnamen moeten geldig zijn voor PropertyAccessor
     * - Onbekende keys zijn alleen toegestaan als $strict = false
     *
     * @param string[] $keys Keys uit de payload
     * @param string[] $allowedProperties Toegestane propertynamen
     * @param bool $strict
     */
    private function validateKeys(array $keys, array $allowedProperties, bool $strict): void
    {
        foreach ($keys as $key) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                throw new BadRequestHttpException("Ongeldige veldnaam: \"$key\"");
            }
        }

        if ($strict) {
            $unknown = array_diff($keys, $allowedProperties);
            if (!empty($unknown)) {
                throw new BadRequestHttpException('Onbekende velden: ' . implode(', ', $unknown));
            }
        }
    }
}