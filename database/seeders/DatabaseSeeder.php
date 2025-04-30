<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Answer;
use App\Models\Leaderboard;
use App\Models\Friendship;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        DB::beginTransaction();

        try {
            // Create users with predefined credentials for testing
            $adminUser = User::create([
                'name' => 'Admin User',
                'email' => 'FayWooAdmin1@faywoo.com',
                'password' => Hash::make('password123'),
                // Removed is_admin flag
            ]);

            $testUser = User::create([
                'name' => 'Test User',
                'email' => 'FayWooUser1@faywoo.com',
                'password' => Hash::make('password123'),
            ]);

            // Create additional random users
            $users = User::factory(8)->create();
            $users->push($adminUser, $testUser);

            // Create friendships
            foreach ($users as $index => $user) {
                // Make each user friends with the next user in the collection (circular)
                $friendIndex = ($index + 1) % $users->count();
                $friend = $users[$friendIndex];

                Friendship::create([
                    'user_id' => $user->id,
                    'friend_id' => $friend->id,
                    'status' => 'accepted',
                ]);

                // Add some pending requests too
                $pendingFriendIndex = ($index + 2) % $users->count();
                $pendingFriend = $users[$pendingFriendIndex];

                Friendship::create([
                    'user_id' => $user->id,
                    'friend_id' => $pendingFriend->id,
                    'status' => 'pending',
                ]);
            }

            // Define quiz categories with real questions
            $quizData = $this->getQuizData();

            // Create quizzes with real questions
            foreach ($quizData as $categoryName => $categoryQuizzes) {
                foreach ($categoryQuizzes as $quizTitle => $quizContent) {
                    // Create the quiz
                    $quiz = Quiz::create([
                        'title' => $quizTitle,
                        'description' => $quizContent['description'],
                        'category' => $categoryName,
                        'user_id' => $users->random()->id,
                        'is_public' => rand(0, 5) > 0, // 5/6 chance of being public
                        'show_correct_answer' => (bool) rand(0, 1),
                        'img_url' => $quizContent['img_url'] ?? 'https://picsum.photos/id/' . rand(1, 100) . '/200/200',
                    ]);

                    // Create questions for this quiz
                    foreach ($quizContent['questions'] as $questionData) {
                        $question = Question::create([
                            'quiz_id' => $quiz->id,
                            'question_text' => $questionData['text'],
                            'question_type' => $questionData['type'],
                            'difficulty' => $questionData['difficulty'],
                            'time_to_answer' => $questionData['time'] ?? 30,
                            'img_url' => $questionData['img_url'] ?? null,
                        ]);

                        // Create answers for this question
                        foreach ($questionData['answers'] as $answerText => $isCorrect) {
                            Answer::create([
                                'question_id' => $question->id,
                                'answer_text' => $answerText,
                                'is_correct' => $isCorrect,
                                'user_id' => $quiz->user_id, // Use the quiz creator as the answer creator
                            ]);
                        }
                    }
                }
            }

            // Create some leaderboard entries
            $quizzes = Quiz::all();
            foreach ($users as $user) {
                // Each user attempts 3-8 quizzes
                foreach (range(1, rand(3, 8)) as $i) {
                    $quiz = $quizzes->random();
                    $score = rand(0, 100);

                    Leaderboard::create([
                        'user_id' => $user->id,
                        'quiz_id' => $quiz->id,
                        'points' => $score,
                        'created_at' => now()->subDays(rand(1, 30)) // Random date in the last month
                    ]);
                }
            }

            // Calculate and update user statistics
            foreach ($users as $user) {
                // Get correct and incorrect answers
                $totalCorrectAnswers = 0; // Default value if column doesn't exist

                // Check if the column exists before trying to sum it
                if (Schema::hasColumn('leaderboards', 'correct_answers')) {
                    $totalCorrectAnswers = Leaderboard::where('user_id', $user->id)->sum('correct_answers');
                }

                $totalIncorrectAnswers = 0; // Default value
                if (Schema::hasColumn('leaderboards', 'incorrect_answers')) {
                    $totalIncorrectAnswers = Leaderboard::where('user_id', $user->id)->sum('incorrect_answers');
                }

                $totalAnswers = $totalCorrectAnswers + $totalIncorrectAnswers;

                // Calculate correct percentage
                $correctPercentage = $totalAnswers > 0
                                     ? round(($totalCorrectAnswers / $totalAnswers) * 100, 1)
                                     : 0;

                // Get quiz stats
                $quizzesAttempted = Leaderboard::where('user_id', $user->id)
                                              ->distinct('quiz_id')
                                              ->count();

                $highestScore = Leaderboard::where('user_id', $user->id)
                                          ->max('points') ?? 0;

                $averageScore = Leaderboard::where('user_id', $user->id)
                                          ->avg('points') ?? 0;

                // Update user with calculated stats
                $user->update([
                    'total_score' => Leaderboard::where('user_id', $user->id)->sum('points'),
                    'correct_answers' => $totalCorrectAnswers,
                    'incorrect_answers' => $totalIncorrectAnswers,
                    'correct_percentage' => $correctPercentage,
                    'total_questions_answered' => $totalAnswers,
                    'total_quizzes_attempted' => $quizzesAttempted,
                    'highest_score' => $highestScore,
                    'average_score' => $averageScore,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Seeder failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get structured quiz data with real questions and answers
     */
    private function getQuizData()
    {
        return [
            'Science' => [
                'Basic Chemistry' => [
                    'description' => 'Test your knowledge of fundamental chemistry concepts.',
                    'img_url' => 'quiz_images/chemistry.jpg',
                    'questions' => [
                        [
                            'text' => 'What is the chemical symbol for gold?',
                            'type' => 'single choice',
                            'difficulty' => 'easy',
                            'time' => 15,
                            'answers' => [
                                'Au' => true,
                                'Ag' => false,
                                'Fe' => false,
                                'Gd' => false,
                            ]
                        ],
                        [
                            'text' => 'Which of these is NOT a noble gas?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 20,
                            'answers' => [
                                'Helium' => false,
                                'Neon' => false,
                                'Nitrogen' => true,
                                'Argon' => false,
                            ]
                        ],
                        [
                            'text' => 'What is the pH of a neutral solution?',
                            'type' => 'single choice',
                            'difficulty' => 'easy',
                            'time' => 15,
                            'answers' => [
                                '0' => false,
                                '7' => true,
                                '14' => false,
                                '1' => false,
                            ]
                        ],
                        [
                            'text' => 'Which element has the highest electronegativity?',
                            'type' => 'single choice',
                            'difficulty' => 'hard',
                            'time' => 30,
                            'answers' => [
                                'Oxygen' => false,
                                'Chlorine' => false,
                                'Fluorine' => true,
                                'Nitrogen' => false,
                            ]
                        ],
                        [
                            'text' => 'Which of these are transition metals?',
                            'type' => 'multiple choice',
                            'difficulty' => 'medium',
                            'time' => 30,
                            'answers' => [
                                'Iron' => true,
                                'Copper' => true,
                                'Calcium' => false,
                                'Sodium' => false,
                            ]
                        ],
                    ]
                ],
                'Human Biology' => [
                    'description' => 'Explore the fascinating systems of the human body.',
                    'img_url' => 'quiz_images/biology.jpg',
                    'questions' => [
                        [
                            'text' => 'Which blood type is known as the universal donor?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 20,
                            'answers' => [
                                'A+' => false,
                                'AB-' => false,
                                'O-' => true,
                                'B+' => false,
                            ]
                        ],
                        [
                            'text' => 'How many chambers does the human heart have?',
                            'type' => 'single choice',
                            'difficulty' => 'easy',
                            'time' => 15,
                            'answers' => [
                                '2' => false,
                                '3' => false,
                                '4' => true,
                                '5' => false,
                            ]
                        ],
                        [
                            'text' => 'Which of these are part of the respiratory system?',
                            'type' => 'multiple choice',
                            'difficulty' => 'medium',
                            'time' => 25,
                            'answers' => [
                                'Lungs' => true,
                                'Trachea' => true,
                                'Liver' => false,
                                'Bronchi' => true,
                            ]
                        ],
                        [
                            'text' => 'What is the largest organ in the human body?',
                            'type' => 'single choice',
                            'difficulty' => 'easy',
                            'time' => 15,
                            'answers' => [
                                'Heart' => false,
                                'Liver' => false,
                                'Skin' => true,
                                'Brain' => false,
                            ]
                        ],
                        [
                            'text' => 'Which hormone regulates blood glucose levels?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 20,
                            'answers' => [
                                'Insulin' => true,
                                'Estrogen' => false,
                                'Testosterone' => false,
                                'Adrenaline' => false,
                            ]
                        ],
                    ]
                ],
            ],
            'History' => [
                'Ancient Civilizations' => [
                    'description' => 'Journey through the remarkable achievements of ancient societies.',
                    'img_url' => 'quiz_images/ancient_history.jpg',
                    'questions' => [
                        [
                            'text' => 'Which ancient civilization built the pyramids at Giza?',
                            'type' => 'single choice',
                            'difficulty' => 'easy',
                            'time' => 15,
                            'answers' => [
                                'Romans' => false,
                                'Greeks' => false,
                                'Egyptians' => true,
                                'Persians' => false,
                            ]
                        ],
                        [
                            'text' => 'Who was the first Emperor of Rome?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 20,
                            'answers' => [
                                'Julius Caesar' => false,
                                'Augustus' => true,
                                'Nero' => false,
                                'Constantine' => false,
                            ]
                        ],
                        [
                            'text' => 'Which of these structures were built by the Maya civilization?',
                            'type' => 'multiple choice',
                            'difficulty' => 'hard',
                            'time' => 30,
                            'answers' => [
                                'Chichen Itza' => true,
                                'Machu Picchu' => false,
                                'Tikal' => true,
                                'The Colosseum' => false,
                            ]
                        ],
                        [
                            'text' => 'The ancient city of Babylon was located in what is now which country?',
                            'type' => 'single choice',
                            'difficulty' => 'hard',
                            'time' => 25,
                            'answers' => [
                                'Egypt' => false,
                                'Turkey' => false,
                                'Iraq' => true,
                                'Iran' => false,
                            ]
                        ],
                        [
                            'text' => 'Approximately when did the Ancient Greek civilization begin?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 20,
                            'answers' => [
                                '500 BCE' => false,
                                '1500 BCE' => false,
                                '800 BCE' => true,
                                '3000 BCE' => false,
                            ]
                        ],
                    ]
                ],
                'World War II' => [
                    'description' => 'Test your knowledge about the most devastating global conflict in history.',
                    'img_url' => 'quiz_images/ww2.jpg',
                    'questions' => [
                        [
                            'text' => 'When did World War II begin?',
                            'type' => 'single choice',
                            'difficulty' => 'easy',
                            'time' => 15,
                            'answers' => [
                                '1939' => true,
                                '1941' => false,
                                '1914' => false,
                                '1945' => false,
                            ]
                        ],
                        [
                            'text' => 'Which event directly led to the United States entering World War II?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 20,
                            'answers' => [
                                'Battle of Britain' => false,
                                'Pearl Harbor attack' => true,
                                'Fall of France' => false,
                                'D-Day invasion' => false,
                            ]
                        ],
                        [
                            'text' => 'Who were the leaders of the Allied Powers during World War II?',
                            'type' => 'multiple choice',
                            'difficulty' => 'medium',
                            'time' => 25,
                            'answers' => [
                                'Winston Churchill' => true,
                                'Franklin D. Roosevelt' => true,
                                'Joseph Stalin' => true,
                                'Adolf Hitler' => false,
                            ]
                        ],
                        [
                            'text' => 'Which city was the first to be attacked with an atomic bomb?',
                            'type' => 'single choice',
                            'difficulty' => 'easy',
                            'time' => 15,
                            'answers' => [
                                'Tokyo' => false,
                                'Hiroshima' => true,
                                'Nagasaki' => false,
                                'Kyoto' => false,
                            ]
                        ],
                        [
                            'text' => 'Operation Barbarossa was Nazi Germany\'s invasion of which country?',
                            'type' => 'single choice',
                            'difficulty' => 'hard',
                            'time' => 20,
                            'answers' => [
                                'France' => false,
                                'United Kingdom' => false,
                                'Poland' => false,
                                'Soviet Union' => true,
                            ]
                        ],
                    ]
                ],
            ],
            'Geography' => [
                'World Capitals' => [
                    'description' => 'Test your knowledge of capital cities around the globe.',
                    'img_url' => 'quiz_images/capitals.jpg',
                    'questions' => [
                        [
                            'text' => 'What is the capital of Australia?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 15,
                            'answers' => [
                                'Sydney' => false,
                                'Melbourne' => false,
                                'Canberra' => true,
                                'Perth' => false,
                            ]
                        ],
                        [
                            'text' => 'What is the capital of Canada?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 15,
                            'answers' => [
                                'Toronto' => false,
                                'Montreal' => false,
                                'Vancouver' => false,
                                'Ottawa' => true,
                            ]
                        ],
                        [
                            'text' => 'Which of these are capital cities?',
                            'type' => 'multiple choice',
                            'difficulty' => 'hard',
                            'time' => 25,
                            'answers' => [
                                'Brasilia' => true,
                                'Zurich' => false,
                                'Wellington' => true,
                                'Barcelona' => false,
                            ]
                        ],
                        [
                            'text' => 'What is the capital of Egypt?',
                            'type' => 'single choice',
                            'difficulty' => 'easy',
                            'time' => 15,
                            'answers' => [
                                'Alexandria' => false,
                                'Cairo' => true,
                                'Luxor' => false,
                                'Giza' => false,
                            ]
                        ],
                        [
                            'text' => 'What is the capital of South Korea?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 15,
                            'answers' => [
                                'Busan' => false,
                                'Incheon' => false,
                                'Seoul' => true,
                                'Daegu' => false,
                            ]
                        ],
                    ]
                ],
                'Natural Wonders' => [
                    'description' => 'Explore the most breathtaking natural landmarks on Earth.',
                    'img_url' => 'quiz_images/nature.jpg',
                    'questions' => [
                        [
                            'text' => 'In which country would you find the Great Barrier Reef?',
                            'type' => 'single choice',
                            'difficulty' => 'easy',
                            'time' => 15,
                            'answers' => [
                                'New Zealand' => false,
                                'Mexico' => false,
                                'Australia' => true,
                                'Japan' => false,
                            ]
                        ],
                        [
                            'text' => 'Which is the highest waterfall in the world?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 20,
                            'answers' => [
                                'Niagara Falls' => false,
                                'Victoria Falls' => false,
                                'Angel Falls' => true,
                                'Iguazu Falls' => false,
                            ]
                        ],
                        [
                            'text' => 'Which of these are major deserts?',
                            'type' => 'multiple choice',
                            'difficulty' => 'medium',
                            'time' => 25,
                            'answers' => [
                                'Sahara' => true,
                                'Gobi' => true,
                                'Amazon' => false,
                                'Atacama' => true,
                            ]
                        ],
                        [
                            'text' => 'Mount Everest is located in which mountain range?',
                            'type' => 'single choice',
                            'difficulty' => 'easy',
                            'time' => 15,
                            'answers' => [
                                'Alps' => false,
                                'Andes' => false,
                                'Himalayas' => true,
                                'Rocky Mountains' => false,
                            ]
                        ],
                        [
                            'text' => 'Which is the largest ocean on Earth?',
                            'type' => 'single choice',
                            'difficulty' => 'easy',
                            'time' => 15,
                            'answers' => [
                                'Atlantic Ocean' => false,
                                'Indian Ocean' => false,
                                'Pacific Ocean' => true,
                                'Arctic Ocean' => false,
                            ]
                        ],
                    ]
                ],
            ],
            'Entertainment' => [
                'Classic Movies' => [
                    'description' => 'Test your knowledge of iconic films that shaped cinema history.',
                    'img_url' => 'quiz_images/movies.jpg',
                    'questions' => [
                        [
                            'text' => 'Who directed the 1975 thriller film "Jaws"?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 20,
                            'answers' => [
                                'Steven Spielberg' => true,
                                'Martin Scorsese' => false,
                                'Francis Ford Coppola' => false,
                                'Alfred Hitchcock' => false,
                            ]
                        ],
                        [
                            'text' => 'Which film won the Academy Award for Best Picture in 1994?',
                            'type' => 'single choice',
                            'difficulty' => 'hard',
                            'time' => 25,
                            'answers' => [
                                'Pulp Fiction' => false,
                                'The Shawshank Redemption' => false,
                                'Forrest Gump' => true,
                                'The Lion King' => false,
                            ]
                        ],
                        [
                            'text' => 'Which of these actors have portrayed James Bond?',
                            'type' => 'multiple choice',
                            'difficulty' => 'medium',
                            'time' => 25,
                            'answers' => [
                                'Sean Connery' => true,
                                'Roger Moore' => true,
                                'Hugh Jackman' => false,
                                'Daniel Craig' => true,
                            ]
                        ],
                        [
                            'text' => 'In "The Wizard of Oz," what was the name of Dorothy\'s dog?',
                            'type' => 'single choice',
                            'difficulty' => 'easy',
                            'time' => 15,
                            'answers' => [
                                'Rex' => false,
                                'Toto' => true,
                                'Buddy' => false,
                                'Lucky' => false,
                            ]
                        ],
                        [
                            'text' => 'Which 1972 film features the line "I\'m gonna make him an offer he can\'t refuse"?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 20,
                            'answers' => [
                                'The Godfather' => true,
                                'Chinatown' => false,
                                'The French Connection' => false,
                                'Taxi Driver' => false,
                            ]
                        ],
                    ]
                ],
            ],
            'Sports' => [
                'Olympic Games' => [
                    'description' => 'Test your knowledge about the world\'s premier sporting event.',
                    'img_url' => 'quiz_images/olympics.jpg',
                    'questions' => [
                        [
                            'text' => 'Where were the first modern Olympic Games held in 1896?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 20,
                            'answers' => [
                                'Paris' => false,
                                'London' => false,
                                'Athens' => true,
                                'Rome' => false,
                            ]
                        ],
                        [
                            'text' => 'Which of these sports were part of the original modern Olympics?',
                            'type' => 'multiple choice',
                            'difficulty' => 'hard',
                            'time' => 30,
                            'answers' => [
                                'Swimming' => true,
                                'Gymnastics' => true,
                                'Basketball' => false,
                                'Tennis' => false,
                            ]
                        ],
                        [
                            'text' => 'Who holds the record for the most Olympic gold medals?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 20,
                            'answers' => [
                                'Usain Bolt' => false,
                                'Michael Phelps' => true,
                                'Simone Biles' => false,
                                'Carl Lewis' => false,
                            ]
                        ],
                        [
                            'text' => 'In which year did women first compete in the modern Olympic Games?',
                            'type' => 'single choice',
                            'difficulty' => 'hard',
                            'time' => 25,
                            'answers' => [
                                '1896' => false,
                                '1900' => true,
                                '1924' => false,
                                '1936' => false,
                            ]
                        ],
                        [
                            'text' => 'Which city has hosted the Summer Olympics three times?',
                            'type' => 'single choice',
                            'difficulty' => 'hard',
                            'time' => 25,
                            'answers' => [
                                'Paris' => false,
                                'Tokyo' => false,
                                'Los Angeles' => false,
                                'London' => true,
                            ]
                        ],
                    ]
                ],
            ],
            'Technology' => [
                'Computer Science Fundamentals' => [
                    'description' => 'Test your knowledge of the basic concepts in computer science.',
                    'img_url' => 'quiz_images/computers.jpg',
                    'questions' => [
                        [
                            'text' => 'What does CPU stand for?',
                            'type' => 'single choice',
                            'difficulty' => 'easy',
                            'time' => 15,
                            'answers' => [
                                'Central Processing Unit' => true,
                                'Computer Personal Unit' => false,
                                'Central Program Utility' => false,
                                'Centralized Processing Utility' => false,
                            ]
                        ],
                        [
                            'text' => 'Which of these are programming languages?',
                            'type' => 'multiple choice',
                            'difficulty' => 'easy',
                            'time' => 20,
                            'answers' => [
                                'Python' => true,
                                'HTML' => true,
                                'Microsoft Word' => false,
                                'JavaScript' => true,
                            ]
                        ],
                        [
                            'text' => 'What does HTTP stand for?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 20,
                            'answers' => [
                                'Hypertext Transfer Protocol' => true,
                                'Hypertext Technical Processing' => false,
                                'High Transfer Text Protocol' => false,
                                'Hypertext Terminal Procedure' => false,
                            ]
                        ],
                        [
                            'text' => 'Which company developed the first commercially successful personal computer?',
                            'type' => 'single choice',
                            'difficulty' => 'hard',
                            'time' => 25,
                            'answers' => [
                                'Microsoft' => false,
                                'IBM' => true,
                                'Apple' => false,
                                'Dell' => false,
                            ]
                        ],
                        [
                            'text' => 'Which data structure follows the Last In, First Out (LIFO) principle?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 20,
                            'answers' => [
                                'Queue' => false,
                                'Stack' => true,
                                'Array' => false,
                                'Linked List' => false,
                            ]
                        ],
                    ]
                ],
                'Internet History' => [
                    'description' => 'Explore the fascinating evolution of the internet and World Wide Web.',
                    'img_url' => 'quiz_images/internet.jpg',
                    'questions' => [
                        [
                            'text' => 'Who is considered the inventor of the World Wide Web?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 20,
                            'answers' => [
                                'Bill Gates' => false,
                                'Tim Berners-Lee' => true,
                                'Steve Jobs' => false,
                                'Mark Zuckerberg' => false,
                            ]
                        ],
                        [
                            'text' => 'In which decade was the first email sent?',
                            'type' => 'single choice',
                            'difficulty' => 'hard',
                            'time' => 25,
                            'answers' => [
                                '1960s' => false,
                                '1970s' => true,
                                '1980s' => false,
                                '1990s' => false,
                            ]
                        ],
                        [
                            'text' => 'Which of these were early popular web browsers?',
                            'type' => 'multiple choice',
                            'difficulty' => 'medium',
                            'time' => 25,
                            'answers' => [
                                'Netscape Navigator' => true,
                                'Internet Explorer' => true,
                                'Opera' => true,
                                'Google Chrome' => false,
                            ]
                        ],
                        [
                            'text' => 'What was the first social media site to reach one million monthly active users?',
                            'type' => 'single choice',
                            'difficulty' => 'hard',
                            'time' => 30,
                            'answers' => [
                                'Facebook' => false,
                                'Twitter' => false,
                                'MySpace' => true,
                                'LinkedIn' => false,
                            ]
                        ],
                        [
                            'text' => 'Which organization maintains web standards?',
                            'type' => 'single choice',
                            'difficulty' => 'hard',
                            'time' => 25,
                            'answers' => [
                                'IEEE' => false,
                                'W3C' => true,
                                'IETF' => false,
                                'ISO' => false,
                            ]
                        ],
                    ]
                ],
            ],
            'General Knowledge' => [
                'Trivia Mix' => [
                    'description' => 'A fun collection of questions from various fields of knowledge.',
                    'img_url' => 'quiz_images/trivia.jpg',
                    'questions' => [
                        [
                            'text' => 'Which planet is closest to the sun?',
                            'type' => 'single choice',
                            'difficulty' => 'easy',
                            'time' => 15,
                            'answers' => [
                                'Venus' => false,
                                'Earth' => false,
                                'Mercury' => true,
                                'Mars' => false,
                            ]
                        ],
                        [
                            'text' => 'Who painted the Mona Lisa?',
                            'type' => 'single choice',
                            'difficulty' => 'easy',
                            'time' => 15,
                            'answers' => [
                                'Vincent van Gogh' => false,
                                'Pablo Picasso' => false,
                                'Leonardo da Vinci' => true,
                                'Michelangelo' => false,
                            ]
                        ],
                        [
                            'text' => 'Which of these are mammals?',
                            'type' => 'multiple choice',
                            'difficulty' => 'easy',
                            'time' => 20,
                            'answers' => [
                                'Dolphin' => true,
                                'Eagle' => false,
                                'Bat' => true,
                                'Crocodile' => false,
                            ]
                        ],
                        [
                            'text' => 'What is the chemical formula for water?',
                            'type' => 'single choice',
                            'difficulty' => 'easy',
                            'time' => 15,
                            'answers' => [
                                'H2O' => true,
                                'CO2' => false,
                                'O2' => false,
                                'H2O2' => false,
                            ]
                        ],
                        [
                            'text' => 'Which is the largest country by land area?',
                            'type' => 'single choice',
                            'difficulty' => 'medium',
                            'time' => 20,
                            'answers' => [
                                'China' => false,
                                'United States' => false,
                                'Canada' => false,
                                'Russia' => true,
                            ]
                        ],
                    ]
                ],
            ],
        ];
    }
}
