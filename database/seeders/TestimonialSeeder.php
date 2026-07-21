<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * The three reviews the carousel shipped with, copied verbatim so seeding
 * produces a visually identical page.
 *
 * Their avatars are hot-linked Unsplash photos, which a seeder cannot download
 * into storage — they go into `avatar_url` rather than `avatar`. Anything an
 * admin uploads later lands in `avatar` and takes precedence.
 */
class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $testimonials = [
            [
                'author_name' => 'Sarah Mitchell',
                'author_role' => 'Marketing Manager',
                'quote' => "The meal plans actually fit my calorie goals and the food is genuinely delicious. I've lost 6kg in three months without ever feeling like I'm on a diet. Delivery is always on time!",
                'avatar_url' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=140&q=80',
                'rating' => 5.0,
                'sort_order' => 1,
            ],
            [
                'author_name' => 'James Carter',
                'author_role' => 'Software Engineer',
                'quote' => 'As a busy dad I never had time to plan healthy meals. MealHub does it for me and the kids love the variety. The credit wallet makes reordering a one-tap thing.',
                'avatar_url' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=140&q=80',
                'rating' => 5.0,
                'sort_order' => 2,
            ],
            [
                'author_name' => 'Aisha Rahman',
                'author_role' => 'Yoga Instructor',
                'quote' => "I have multiple food allergies and MealHub's health profile filters out everything I can't eat. Finally a service I can trust. Customer support is fantastic too.",
                'avatar_url' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=140&q=80',
                'rating' => 4.5,
                'sort_order' => 3,
            ],
        ];

        foreach ($testimonials as $testimonial) {
            Testimonial::firstOrCreate(
                ['author_name' => $testimonial['author_name']],
                $testimonial + ['is_published' => true, 'avatar' => null],
            );
        }
    }
}
