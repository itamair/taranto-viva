<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\FieldType;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "phone_number",
 *   type_sdl = "PhoneNumber",
 * )
 */
class PhoneNumberItem extends TelephoneItem {}
