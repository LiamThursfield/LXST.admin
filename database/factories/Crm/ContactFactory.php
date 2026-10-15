<?php

namespace Database\Factories\Crm;

use App\Models\Crm\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /**
     * Define the model's default state (a genuine contact with no marketing opt-ins).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'is_spam' => false,
            'title' => fake()->optional()->title(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'telephone' => fake()->numerify('07### ######'),
            'email' => fake()->unique()->safeEmail(),
            'marketing_email' => false,
            'marketing_sms' => false,
            'marketing_telephone' => false,
        ];
    }

    /**
     * A contact marked as spam
     */
    public function spam(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_spam' => true,
        ]);
    }

    /**
     * A contact who has opted in to every kind of marketing
     */
    public function optedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'marketing_email' => true,
            'marketing_sms' => true,
            'marketing_telephone' => true,
        ]);
    }
}
