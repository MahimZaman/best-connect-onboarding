<?php

namespace BestConnectOnboarding\Forms;

defined('ABSPATH') || exit;

class FormRepository
{

    /**
     * @var \wpdb
     */
    private $wpdb;

    /**
     * @var string
     */
    private $table;

    private array $validation_errors = [];

    public function __construct()
    {
        global $wpdb;

        $this->wpdb  = $wpdb;
        $this->table = $wpdb->prefix . 'bcob_forms';
    }

    public function get(int $id)
    {
        $id = absint($id);

        if (! $id) {
            return null;
        }

        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $id
            )
        );
    }

    public function get_by_slug(string $slug)
    {
        $slug = sanitize_title($slug);

        if (empty($slug)) {
            return null;
        }

        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE slug = %s",
                $slug
            )
        );
    }

    public function get_all(array $args = []): array
    {

        $defaults = [
            'status' => '',
            'limit'  => 100,
            'offset' => 0,
            'order'  => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT * FROM {$this->table}";

        if (! empty($args['status'])) {
            $sql .= $this->wpdb->prepare(
                " WHERE status = %s",
                $args['status']
            );
        }

        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql .= $this->wpdb->prepare(
            " ORDER BY id {$order} LIMIT %d OFFSET %d",
            absint($args['limit']),
            absint($args['offset'])
        );

        return $this->wpdb->get_results($sql);
    }

    public function get_count(
        string $status = ''
    ): int {
        if ($status !== '') {
            return (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*)
                FROM {$this->table}
                WHERE status = %s",
                    sanitize_key($status)
                )
            );
        }

        return (int) $this->wpdb->get_var(
            "SELECT COUNT(*)
        FROM {$this->table}"
        );
    }

    public function get_validation_errors(): array
    {
        return $this->validation_errors;
    }

    private function normalize_schema(
        array $schema
    ): array {
        if (
            !isset($schema['steps']) ||
            !is_array($schema['steps'])
        ) {
            return $schema;
        }

        foreach (
            $schema['steps']
            as $step_index => $step
        ) {
            if (
                !is_array($step) ||
                !isset($step['fields']) ||
                !is_array($step['fields'])
            ) {
                continue;
            }

            foreach (
                $step['fields']
                as $field_index => $field
            ) {
                if (!is_array($field)) {
                    continue;
                }

                if (
                    empty($field['id']) ||
                    !is_scalar($field['id'])
                ) {
                    continue;
                }

                if (
                    !isset($field['key']) ||
                    !is_string($field['key']) ||
                    trim($field['key']) === ''
                ) {
                    $schema['steps'][$step_index]['fields'][$field_index]['key'] =
                        (string) $field['id'];
                }
            }
        }

        return $schema;
    }

    private function validate_schema(
        array $schema
    ): bool {
        $this->validation_errors = [];

        if (
            !isset($schema['steps']) ||
            !is_array($schema['steps'])
        ) {
            $this->validation_errors[] =
                __(
                    'Form schema: steps must be an array.',
                    'best-connect-onboarding'
                );

            return false;
        }

        foreach (
            $schema['steps']
            as $step_index => $step
        ) {
            $step_number =
                $step_index + 1;

            if (!is_array($step)) {
                $this->validation_errors[] =
                    sprintf(
                        __(
                            'Step %d: step data must be an array.',
                            'best-connect-onboarding'
                        ),
                        $step_number
                    );

                continue;
            }

            $step_title =
                sanitize_text_field(
                    $step['title'] ?? ''
                );

            $step_reference =
                $step_title !== ''
                ? sprintf(
                    'Step %d "%s"',
                    $step_number,
                    $step_title
                )
                : sprintf(
                    'Step %d',
                    $step_number
                );

            if (
                isset($step['fields']) &&
                !is_array($step['fields'])
            ) {
                $this->validation_errors[] =
                    sprintf(
                        __(
                            '%s: fields must be an array.',
                            'best-connect-onboarding'
                        ),
                        $step_reference
                    );
            }

            if (
                isset($step['conditions']) &&
                !is_array($step['conditions'])
            ) {
                $this->validation_errors[] =
                    sprintf(
                        __(
                            '%s: conditions must be an array.',
                            'best-connect-onboarding'
                        ),
                        $step_reference
                    );
            }

            if (
                !isset($step['fields']) ||
                !is_array($step['fields'])
            ) {
                continue;
            }

            foreach (
                $step['fields']
                as $field_index => $field
            ) {
                $field_number =
                    $field_index + 1;

                if (!is_array($field)) {
                    $this->validation_errors[] =
                        sprintf(
                            __(
                                '%1$s, field %2$d: field data must be an array.',
                                'best-connect-onboarding'
                            ),
                            $step_reference,
                            $field_number
                        );

                    continue;
                }

                $field_label =
                    sanitize_text_field(
                        $field['label'] ??
                            $field['id'] ??
                            $field['key'] ??
                            ''
                    );

                $field_reference =
                    $field_label !== ''
                    ? sprintf(
                        '%s, field %d "%s"',
                        $step_reference,
                        $field_number,
                        $field_label
                    )
                    : sprintf(
                        '%s, field %d',
                        $step_reference,
                        $field_number
                    );

                if (
                    empty($field['id']) ||
                    !is_scalar($field['id'])
                ) {
                    $this->validation_errors[] =
                        sprintf(
                            __(
                                '%s: field ID is missing or invalid.',
                                'best-connect-onboarding'
                            ),
                            $field_reference
                        );
                }

                if (
                    empty($field['key']) ||
                    !is_string($field['key'])
                ) {
                    $this->validation_errors[] =
                        sprintf(
                            __(
                                '%s: field key is missing or invalid.',
                                'best-connect-onboarding'
                            ),
                            $field_reference
                        );
                }

                if (
                    empty($field['type']) ||
                    !is_string($field['type'])
                ) {
                    $this->validation_errors[] =
                        sprintf(
                            __(
                                '%s: field type is missing or invalid.',
                                'best-connect-onboarding'
                            ),
                            $field_reference
                        );
                }

                if (
                    isset($field['options']) &&
                    !is_array($field['options'])
                ) {
                    $this->validation_errors[] =
                        sprintf(
                            __(
                                '%s: options must be an array.',
                                'best-connect-onboarding'
                            ),
                            $field_reference
                        );
                }

                if (
                    isset($field['validation']) &&
                    !is_array($field['validation'])
                ) {
                    $this->validation_errors[] =
                        sprintf(
                            __(
                                '%s: validation rules must be an array.',
                                'best-connect-onboarding'
                            ),
                            $field_reference
                        );
                }

                if (
                    isset($field['conditions']) &&
                    !is_array($field['conditions'])
                ) {
                    $this->validation_errors[] =
                        sprintf(
                            __(
                                '%s: conditions must be an array.',
                                'best-connect-onboarding'
                            ),
                            $field_reference
                        );
                }

                if (
                    isset($field['settings']) &&
                    !is_array($field['settings'])
                ) {
                    $this->validation_errors[] =
                        sprintf(
                            __(
                                '%s: settings must be an array.',
                                'best-connect-onboarding'
                            ),
                            $field_reference
                        );
                }
            }
        }

        return empty($this->validation_errors);
    }

    public function insert(array $data)
    {
        $this->validation_errors = [];

        $name = sanitize_text_field($data['name'] ?? '');

        if (empty($name)) {
            return false;
        }

        $form_schema =
            $data['form_schema']
            ?? [];

        if (!is_array($form_schema)) {
            $this->validation_errors = [
                __(
                    'Form schema must be an array.',
                    'best-connect-onboarding'
                ),
            ];

            return false;
        }

        $form_schema =
            $this->normalize_schema(
                $form_schema
            );

        if (
            !$this->validate_schema(
                $form_schema
            )
        ) {
            return false;
        }

        $slug = ! empty($data['slug'])
            ? sanitize_title($data['slug'])
            : $this->generate_unique_slug($name);

        $inserted = $this->wpdb->insert(
            $this->table,
            [
                'name'         => $name,
                'slug'         => $slug,
                'status'       => sanitize_key($data['status'] ?? 'draft'),
                'form_schema'  => wp_json_encode($form_schema),
                'created_at'   => current_time('mysql'),
                'updated_at'   => current_time('mysql'),
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        if (! $inserted) {
            return false;
        }

        return (int) $this->wpdb->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        $this->validation_errors = [];

        $id = absint($id);

        if (! $this->exists($id)) {
            return false;
        }

        $update = [];

        $format = [];

        if (isset($data['name'])) {
            $update['name'] = sanitize_text_field($data['name']);
            $format[]       = '%s';
        }

        if (isset($data['slug'])) {
            $update['slug'] = sanitize_title($data['slug']);
            $format[]       = '%s';
        }

        if (isset($data['status'])) {
            $update['status'] = sanitize_key($data['status']);
            $format[]         = '%s';
        }

        if (isset($data['form_schema'])) {
            if (
                !is_array(
                    $data['form_schema']
                )
            ) {
                $this->validation_errors = [
                    __(
                        'Form schema must be an array.',
                        'best-connect-onboarding'
                    ),
                ];

                return false;
            }

            $form_schema =
                $this->normalize_schema(
                    $data['form_schema']
                );

            if (
                !$this->validate_schema(
                    $form_schema
                )
            ) {
                return false;
            }

            $update['form_schema'] =
                wp_json_encode(
                    $form_schema
                );

            $format[] = '%s';
        }

        $update['updated_at'] = current_time('mysql');
        $format[]             = '%s';

        return false !== $this->wpdb->update(
            $this->table,
            $update,
            [
                'id' => $id,
            ],
            $format,
            [
                '%d',
            ]
        );
    }

    public function delete(int $id): bool
    {

        $id = absint($id);

        if (! $this->exists($id)) {
            return false;
        }

        return false !== $this->wpdb->delete(
            $this->table,
            [
                'id' => $id,
            ],
            [
                '%d',
            ]
        );
    }

    public function exists(int $id): bool
    {

        $id = absint($id);

        return (bool) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE id = %d",
                $id
            )
        );
    }

    public function slug_exists(string $slug, int $exclude = 0): bool
    {

        $slug    = sanitize_title($slug);
        $exclude = absint($exclude);

        if ($exclude) {
            return (bool) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM {$this->table} WHERE slug = %s AND id != %d",
                    $slug,
                    $exclude
                )
            );
        }

        return (bool) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table} WHERE slug = %s",
                $slug
            )
        );
    }

    public function generate_unique_slug(string $name): string
    {

        $slug = sanitize_title($name);

        if (empty($slug)) {
            $slug = 'form';
        }

        $original = $slug;
        $i        = 2;

        while ($this->slug_exists($slug)) {
            $slug = "{$original}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function duplicate(int $id)
    {

        $form = $this->get($id);

        if (! $form) {
            return false;
        }

        return $this->insert(
            [
                'name'        => $form->name . ' Copy',
                'form_schema' => json_decode($form->form_schema, true),
                'status'      => $form->status,
            ]
        );
    }
}
