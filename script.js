const card = document.getElementById('card');

let degree = 0;
let currentAudioUrl = "";

let set = "vocabulary";
let currentId = -1;

window.onload = () => {
    const defaultSet = document.getElementById('vocabulary-category').value;
    changeVocabularySet(defaultSet);
}

// 翻動牌面
function flipCard(event) {
    // 避免點擊發音按鈕時候翻牌
    if (event.target.closest('#audio')) return;

    card.style.transition = "transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)";

    const rectangle = card.getBoundingClientRect();
    const clickPosition = event.clientX - rectangle.left - rectangle.width / 2;

    if (clickPosition >= 0) {
        degree += 180;
    } else {
        degree -= 180;
    }

    card.style.transform = `rotateY(${degree}deg)`;
};

// 翻牌歸零次數歸零 避免記憶體滿
card.addEventListener('transitionend', () => {
    card.style.transition = "none";

    degree %= 360;

    // 確保角度為正
    if (degree < 0) degree += 360;
    card.style.transform = `rotateY(${degree}deg)`;
});

// 塞入單字訊息動畫
function fetchAndRenderCard(action) {
    const url = `api.php?action=${action}&set=${set}`;

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.status == 'success') {
                // 目前卡片正面或是背面
                const isBack = (Math.abs(degree) % 360 == 180);

                if (isBack) {
                    card.style.transition = "transform 0.5s ease-out";
                    degree = 0;
                    card.style.transform = `rotateY(${degree}deg)`;

                    setTimeout(() => {
                        fillCardContent(data);
                    }, 200);
                } else {
                    fillCardContent(data);
                }
            } else {
                document.getElementById('word').innerText = 'fetch data fail.';
            }
        })
        .catch(error => {
            console.log('error: ', error);
            document.getElementById('word').innerText = 'connection fail.'
        });
}

function fillCardContent(data) {
    currentId = data.id;
    document.getElementById('word').innerText = data.word;
    document.getElementById('part-of-speech').innerText = data.part_of_speech;
    document.getElementById('phonetic').innerText = data.phonetic;
    document.getElementById('definition').innerText = data.definition;
    document.getElementById('translation').innerText = data.translation;
    currentAudioUrl = data.audio;
}

// 抽新張牌
function drawCard() {
    fetchAndRenderCard('draw');
};

// 重新洗牌
function shuffleCard() {
    fetchAndRenderCard('shuffle');
};

// 播放聲音
function playAudio(event) {
    event.stopPropagation();
    if (currentAudioUrl) {
        if (currentAudioUrl.includes('.mp3')) {
            const audio = new Audio(currentAudioUrl);
            audio.play();
        } else {
            fetch(currentAudioUrl).then(response => response.json()).then(data => {
                if (data[0]?.phonetics?.[0]?.audio) {
                    new Audio(data[0].phonetics[0].audio).play();
                } else {
                    alert("this word does not have phonetic data");
                }
            });
        }
    }
};

// 更換牌組
function changeVocabularySet(newSet) {
    set = newSet;

    fetchAndRenderCard('shuffle');
};

// 勾選牌卡動畫
function clickCheckbox(event){
    event.stopPropagation();

    const label = document.getElementById('learned-checkbox');

    if(label){
        label.classList.toggle('checked');

        if(label.classList.contains('checked')){
            isLearned(event);
        }else {
            cancelIsLearned(event);
        }
    }

    

    if(label.classList.contains('checked')){
        isLearned(event);
    }
}

// 勾選已學會的牌卡
function isLearned(event){
    if(currentId < 0){
        alert("have not choose a card.");
        return;
    }

    const url = `api.php?action=learned&id=${currentId}&set=${set}`;

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if(data.status == 'success'){
                alert("you have learned this card.");
            }else {
                alert("action fail: " + data.message);
            }
        })
        .catch(error => {
            console.error('error: ', error);
            alert("connection fail.");
        });
}

// 取消勾選已學會的牌卡
function isLearned(event){
    if(currentId < 0){
        alert("have not choose a card.");
        return;
    }

    const url = `api.php?action=forgot&id=${currentId}&set=${set}`;

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if(data.status == 'success'){
                alert("you have cancel this card.");
            }else {
                alert("action fail: " + data.message);
            }
        })
        .catch(error => {
            console.error('error: ', error);
            alert("connection fail.");
        });
}