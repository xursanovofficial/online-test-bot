<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Test yechish va Reyting</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                gold: {
                    light: '#FFD700',
                    DEFAULT: '#CFB53B',
                    dark: '#B8860B',
                },
            },
        },
    },
}
</script>
</head>
<body class="bg-black text-gold-light min-h-screen flex flex-col">

<!-- Kontent -->
<div id="homeContent" class="flex-1 flex items-center justify-center p-4">
    <div class="w-full max-w-md sm:max-w-xl space-y-8 p-6 sm:p-10 bg-gray-900 bg-opacity-80 backdrop-filter backdrop-blur-xl rounded-3xl shadow-2xl border border-gold">
        <h2 class="text-3xl sm:text-4xl font-extrabold text-center text-gold mb-8">
            Testlar bilan ishlash
        </h2>
        <form id="testForm" method="GET" class="space-y-8">
            <div class="flex flex-col space-y-4">
                <label for="rangeMin" class="text-base sm:text-lg font-medium text-gold-light">Sonlar oralig'ini kiriting:</label>
                <div class="flex items-center space-x-4">
                    <input type="number" name="rangeMin" id="rangeMin" placeholder="dan" required class="w-full sm:w-24 p-3 text-black rounded-lg bg-gold-light focus:bg-white transition-all duration-300 focus:ring-2 focus:ring-gold focus:outline-none">
                    <span class="text-xl sm:text-2xl font-bold text-gold">-</span>
                    <input type="number" name="rangeMax" id="rangeMax" placeholder="gacha" required class="w-full sm:w-24 p-3 text-black rounded-lg bg-gold-light focus:bg-white transition-all duration-300 focus:ring-2 focus:ring-gold focus:outline-none">
                </div>
            </div>

            <div class="flex flex-col space-y-4">
                <label class="text-base sm:text-lg font-medium text-gold-light">Kategoriyani tanlang:</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($questionNames as $id => $name)
                        <button type="button"
                            onclick="selectCategory('{{ $id }}')"
                            class="category-btn px-4 py-3 bg-gradient-to-r from-gold to-gold-dark text-black hover:from-gold-dark hover:to-gold rounded-lg transition-all duration-300 transform hover:scale-105 shadow-md flex items-center justify-center"
                            data-category="{{ $id }}">
                            <span class="text-sm sm:text-base">{{ ucwords(str_replace('_', ' ', $name)) }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <input type="hidden" name="question_name_id" id="categoryInput" required>

            <button type="submit" class="w-full py-3 px-6 bg-gradient-to-r from-gold to-gold-dark text-black hover:from-gold-dark hover:to-gold rounded-lg text-base sm:text-lg font-semibold transition-all duration-300 transform hover:scale-105 shadow-md">
                Boshlash
            </button>
        </form>
    </div>
</div>

<!-- Reyting bo'limi -->
<div id="ratingContent" class="flex-1 p-4 hidden">
    <div class="max-w-3xl mx-auto space-y-6">
        <h2 class="text-3xl font-extrabold text-gold text-center mb-4">Top Reytinglar</h2>

        <!-- Jadval sarlavhasi -->
        <div class="grid grid-cols-4 gap-2 p-3 bg-gray-800 rounded-t-lg border border-gold font-semibold text-gold">
            <div>User</div>
            <div>To'g'ri javob</div>
            <div>Noto'g'ri javob</div>
            <div>Jami javob</div>
        </div>

        <!-- Top userlar -->
        <div id="topRatings" class="space-y-1"></div>

        <!-- O'z useri -->
        <h2 class="text-2xl font-bold text-gold mt-8 text-center">Sizning Reytingingiz</h2>
        <div id="userRating" class="space-y-1 mt-2"></div>
    </div>
</div>

<!-- Pastki panel -->
<nav class="fixed bottom-0 left-0 w-full bg-gray-900 border-t border-gray-700 flex">
    <button id="homeBtn" class="flex-1 py-4 flex flex-col items-center justify-center hover:bg-gray-800 transition">
        <div class="w-8 h-8 bg-gold-light rounded-lg flex items-center justify-center mb-1">🏠</div>
        <span class="text-xs sm:text-sm">Home</span>
    </button>
    <button id="ratingBtn" class="flex-1 py-4 flex flex-col items-center justify-center hover:bg-gray-800 transition">
        <div class="w-8 h-8 bg-gold-light rounded-lg flex items-center justify-center mb-1">⭐</div>
        <span class="text-xs sm:text-sm">Reyting</span>
    </button>
</nav>

<script>
function selectCategory(categoryId){
    document.getElementById('categoryInput').value = categoryId;
    document.querySelectorAll('.category-btn').forEach(btn=>{
        btn.classList.remove('ring-2','ring-gold','bg-black','text-gold');
        btn.classList.add('bg-gradient-to-r','from-gold','to-gold-dark','text-black','hover:from-gold-dark','hover:to-gold');
    });
    const selectedBtn=document.querySelector(`[data-category="${categoryId}"]`);
    selectedBtn.classList.remove('bg-gradient-to-r','from-gold','to-gold-dark','text-black','hover:from-gold-dark','hover:to-gold');
    selectedBtn.classList.add('ring-2','ring-gold','bg-black','text-gold');
}

document.getElementById('testForm').addEventListener('submit',function(e){
    e.preventDefault();
    const rangeMin=document.getElementById('rangeMin').value;
    const rangeMax=document.getElementById('rangeMax').value;
    const categoryId=document.getElementById('categoryInput').value;
    if(!rangeMin || !rangeMax || !categoryId){ alert('Iltimos, barcha maydonlarni to\'ldiring'); return; }
    const urlParams = new URLSearchParams(window.location.search);
    const chatId = urlParams.get('chat_id');
    let url=`/questions?start_number=${rangeMin}&end_number=${rangeMax}&question_name_id=${categoryId}`;
    if(chatId){ url+=`&chat_id=${chatId}`; }
    window.location.href=url;
});

// Pastki panel tugmalari
const homeBtn = document.getElementById('homeBtn');
const ratingBtn = document.getElementById('ratingBtn');
const homeContent = document.getElementById('homeContent');
const ratingContent = document.getElementById('ratingContent');

homeBtn.addEventListener('click',()=>{
    homeContent.classList.remove('hidden');
    ratingContent.classList.add('hidden');
});

ratingBtn.addEventListener('click', ()=>{
    homeContent.classList.add('hidden');
    ratingContent.classList.remove('hidden');

    const urlParams = new URLSearchParams(window.location.search);
    const chatId = urlParams.get('chat_id') || '';

    fetch(`/top-ratings?chat_id=${chatId}`)
        .then(res => res.json())
        .then(data => {
            const topRatings = document.getElementById('topRatings');
            const userRating = document.getElementById('userRating');
            topRatings.innerHTML = '';
            userRating.innerHTML = '';

            // Top 10 userlar
            data.top.forEach(u => {
                topRatings.innerHTML += `
                <div class="grid grid-cols-4 gap-2 p-3 bg-gray-700 rounded border border-gold">
                    <div>${u.full_name}</div>
                    <div class="text-green-400 font-bold">${u.correct_answer}</div>
                    <div class="text-red-500 font-bold">${u.incorrect_answer}</div>
                    <div>${u.total_answer}</div>
                </div>`;
            });

            // O'z useri
            if(data.user){
                userRating.innerHTML += `
                <div class="grid grid-cols-4 gap-2 p-3 bg-gray-700 rounded border border-gold">
                    <div>${data.user.full_name}</div>
                    <div class="text-green-400 font-bold">${data.user.correct_answer}</div>
                    <div class="text-red-500 font-bold">${data.user.incorrect_answer}</div>
                    <div>${data.user.total_answer}</div>
                </div>`;
            }
        }).catch(err => console.error(err));
});


</script>
</body>
</html>
