<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Test savollar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body
    class="bg-white dark:bg-black text-black dark:text-white min-h-screen py-12 px-4 sm:px-6 lg:px-8 transition-colors duration-300">
    <div class="fixed top-4 right-4 flex space-x-2">
        <button onclick="setTheme('light')"
            class="p-2 rounded-full bg-gray-200 dark:bg-gray-800 text-black dark:text-white">
            <i data-lucide="sun" class="w-6 h-6"></i>
        </button>
        <button onclick="setTheme('dark')"
            class="p-2 rounded-full bg-gray-200 dark:bg-gray-800 text-black dark:text-white">
            <i data-lucide="moon" class="w-6 h-6"></i>
        </button>
    </div>
    <div class="max-w-3xl mx-auto space-y-8">
        <h2 class="text-4xl font-extrabold text-center mb-8 text-black dark:text-white">
            Test savollari
        </h2>
        @foreach ($questions as $index => $question)
            @if ($question['active'])   
                <div
                    class="bg-gray-100 dark:bg-gray-900 bg-opacity-50 backdrop-filter backdrop-blur-lg rounded-xl shadow-lg p-6 space-y-4">
                    <h3 class="text-xl font-bold text-black dark:text-white">{{ $question['test_number'] }}.
                        {{ $question['title'] }}</h3>
                    <div class="space-y-2">
                        @foreach (['a', 'b', 'c', 'd'] as $variant)
                            <label
                                class="flex items-center space-x-3 p-2 rounded-md hover:bg-gray-200 dark:hover:bg-gray-800 transition duration-150 ease-in-out"
                                id="label_{{ $question['id'] }}_{{ $variant }}">
                                <input type="radio" name="question_{{ $question['id'] }}" value="{{ $variant }}"
                                    class="form-radio h-5 w-5 text-pink-600" onchange="checkAnswer(this)">
                                <span
                                    class="text-gray-700 dark:text-gray-300">{{ $question[$variant . '_variant'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-2 text-sm font-semibold hidden" id="feedback_{{ $question['id'] }}"></div>
                </div>
            @endif
        @endforeach
        <div class="flex justify-center mt-8">
            <button onclick="showResults()"
                class="py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-pink-600 hover:bg-pink-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pink-500">
                Testni yakunlash
            </button>
        </div>
    </div>

    <!-- Results Modal -->
    <div id="resultsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Test Natijalari</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Jami savollar: <span id="totalQuestions" class="font-bold"></span>
                    </p>
                    <p class="text-sm text-green-500 mt-2">
                        To'g'ri javoblar: <span id="correctAnswers" class="font-bold"></span>
                    </p>
                    <p class="text-sm text-red-500 mt-2">
                        Noto'g'ri javoblar: <span id="incorrectAnswers" class="font-bold"></span>
                    </p>
                </div>
                <div class="items-center px-4 py-3">
                    <button id="closeModal" onclick="closeModal(); goToHomePage();" class="px-4 py-2 bg-pink-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-pink-600 focus:outline-none focus:ring-2 focus:ring-pink-300">
                        Yopish
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let questions = @json($questions);
        let correctAnswers = @json($correctAnswers);
        let chatId = @json($chatId);
        let userAnswers = {};

        function checkAnswer(input) {
            const questionDiv = input.closest('div').parentElement;
            const feedbackDiv = questionDiv.querySelector('div[id^="feedback_"]');
            // const questionId = input.name.split('_')[1];
            const questionId = parseInt(input.name.split('_')[1]);

            const correctAnswer = correctAnswers[questionId];

            userAnswers[questionId] = input.value;

            // Reset all labels to default state
            questionDiv.querySelectorAll('label').forEach(label => {
                label.classList.remove('bg-blue-100', 'dark:bg-blue-900', 'bg-green-100', 'dark:bg-green-900');
            });

            // Highlight user's selection
            const selectedLabel = document.getElementById(`label_${questionId}_${input.value}`);
            selectedLabel.classList.add('bg-blue-100', 'dark:bg-blue-900');

            if (input.value === correctAnswer) {
                feedbackDiv.textContent = "To'g'ri javob!";
                feedbackDiv.classList.remove('text-red-500');
                feedbackDiv.classList.add('text-green-500');
                questionDiv.classList.add('border-2', 'border-green-500');
            } else {
                const correctLabel = document.getElementById(`label_${questionId}_${correctAnswer}`);
                correctLabel.classList.remove('bg-blue-100', 'dark:bg-blue-900');
                correctLabel.classList.add('bg-green-100', 'dark:bg-green-900');

                feedbackDiv.textContent = "Noto'g'ri javob";
                feedbackDiv.classList.remove('text-green-500');
                feedbackDiv.classList.add('text-red-500');
                questionDiv.classList.remove('border-2', 'border-green-500');
                questionDiv.classList.add('border-2', 'border-red-500');
            }

            feedbackDiv.classList.remove('hidden');

            questionDiv.querySelectorAll('input[type="radio"]').forEach(radio => {
                radio.disabled = true;
            });
        }

        function showResults() {
    let correctCount = 0;
    let incorrectCount = 0;

    Object.keys(userAnswers).forEach(questionId => {
        if (userAnswers[questionId] === correctAnswers[questionId]) {
            correctCount++;
        } else {
            incorrectCount++;
        }
    });

    const totalAnswered = Object.keys(userAnswers).length; // ✅ Faqat ishlaganlar soni

    document.getElementById('totalQuestions').textContent = totalAnswered;
    document.getElementById('correctAnswers').textContent = correctCount;
    document.getElementById('incorrectAnswers').textContent = incorrectCount;

    document.getElementById('resultsModal').classList.remove('hidden');

    // 🔥 BACKENDGA YUBORISH
    fetch('/save-rating', {
        method: 'POST',
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            correct_answer: correctCount,
            incorrect_answer: incorrectCount,
            total_answer: totalAnswered,   // ✅ Endi faqat ishlagan savollar soni ketadi
            chat_id: chatId
        })
    }).then(res => res.json())
      .then(data => {
          console.log("Saqlash OK:", data);
      }).catch(err => {
          console.error("Xatolik:", err);
      });
}



        function closeModal() {
            document.getElementById('resultsModal').classList.add('hidden');
        }

        function goToHomePage() {
            window.location.href = '/?chat_id=' + encodeURIComponent(chatId);
        }

        function setTheme(theme) {
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }

        // Initialize Lucide icons
        lucide.createIcons();

        // Set initial theme based on user preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            setTheme('dark');
        } else {
            setTheme('light');
        }
    </script>
</body>

</html>

