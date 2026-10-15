<?php

namespace Database\Factories\Crm;

use App\Models\Crm\Form;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Form>
 */
class FormFactory extends Factory
{
    protected $model = Form::class;

    /**
     * Define the model's default state (a form with no fields or marketing consent).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'email_recipients' => [fake()->safeEmail()],
            'marketing_email' => false,
            'marketing_sms' => false,
            'marketing_telephone' => false,
            'submit_button_text' => null,
            'redirect_url' => null,
            'success_message' => fake()->sentence(),
        ];
    }

    /**
     * A form asking for every kind of marketing consent
     */
    public function withMarketing(): static
    {
        return $this->state(fn (array $attributes) => [
            'marketing_email' => true,
            'marketing_sms' => true,
            'marketing_telephone' => true,
        ]);
    }
}
