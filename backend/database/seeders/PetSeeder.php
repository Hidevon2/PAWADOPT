<?php

namespace Database\Seeders;

use App\Models\Pet;
use Illuminate\Database\Seeder;

class PetSeeder extends Seeder
{
    public function run(): void
    {
        $pets = [
            ['willow', 'Willow', 'Cats', 'Domestic Shorthair', 18, 'Female', 'Small', 'Quezon City', 'A curious window watcher with the softest purr. Willow takes a little time to settle in, then loves to curl up beside you while you read. A quiet home would help her bloom.', ['Gentle', 'Quiet companion', 'Litter trained'], 500],
            ['biscuit', 'Biscuit', 'Dogs', 'Golden Retriever Mix', 24, 'Male', 'Large', 'Makati', 'Biscuit believes every day is a good day for a walk. This big-hearted companion knows his basic cues and would love a family with time for play, training, and plenty of affection.', ['Playful', 'Loves walks', 'Social'], 1200],
            ['luna', 'Luna', 'Cats', 'Domestic Shorthair', 8, 'Female', 'Small', 'Pasig', 'A tiny explorer with a big personality. Luna loves feather toys, sunny afternoons, and investigating anything new. She would enjoy a patient adopter who can give her plenty of playtime.', ['Curious', 'Playful', 'Litter trained'], 500],
            ['milo', 'Milo', 'Dogs', 'Pug Mix', 14, 'Male', 'Small', 'Quezon City', 'Milo is your future walking buddy and enthusiastic welcome-home committee. He is learning his basic cues and would thrive with a patient family who can continue his training.', ['Friendly', 'Active', 'Eager to learn'], 800],
            ['echo', 'Echo', 'Birds', 'Parakeet', 20, 'Male', 'Small', 'Makati', 'Echo fills the morning with cheerful little songs. He enjoys a roomy enclosure, safe supervised flight time, and a companion who understands the daily care birds need.', ['Sociable', 'Chatty', 'Curious'], 300],
            ['olive', 'Olive', 'Rabbits', 'Dwarf Mix', 9, 'Female', 'Small', 'Pasig', 'Olive is a gentle soul who enjoys fresh hay and a cozy hiding spot. Give her a rabbit-safe indoor space and a little patience, and she will soon be hopping over to say hello.', ['Gentle', 'Curious', 'Indoor home'], 400],
            ['cedar', 'Cedar', 'Rabbits', 'Dwarf Mix', 6, 'Male', 'Small', 'Mandaluyong', 'Cedar is a bright little bunny with a love for exploring. He enjoys tunnels, leafy greens, and room to stretch his legs. He needs a rabbit-savvy adopter or someone willing to learn.', ['Playful', 'Adventurous', 'Indoor home'], 400],
            ['sage', 'Sage', 'Small pets', 'Guinea Pig', 5, 'Female', 'Small', 'Quezon City', 'Small paws, enormous personality. Sage perks up at the rustle of fresh veggies and loves exploring her tunnels. She needs a spacious enclosure, daily hay, and a compatible guinea pig friend.', ['Gentle', 'Loves veggies', 'Social'], 250],
        ];
        foreach ($pets as [$slug, $name, $species, $breed, $age, $gender, $size, $location, $description, $traits, $fee]) {
            Pet::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'species' => $species, 'breed' => $breed, 'age_months' => $age,
                'gender' => $gender, 'size' => $size, 'location' => $location, 'description' => $description,
                'traits' => $traits, 'image' => '/images/'.$slug.'.jpg', 'adoption_fee' => $fee, 'status' => 'available',
            ]);
        }
    }
}
