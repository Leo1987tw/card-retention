<style>
    * {
        box-sizing: border-box;
        margin: 0px;
        padding: 0px;
    }

    .container {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        margin: 20px auto;
        padding: 20px;
    }

    .card-board {
        perspective: 400px;
        width: 400px;
        height: 400px;
        margin: 30px;
        cursor: pointer;
    }

    .card {
        position: relative;
        width: 400px;
        height: 400px;
        box-shadow: 0px 10px 25px -5px rgba(0, 0, 0, 0.1), 0px 8px 10px -6px rgba(0, 0, 0, 0.1);
        transform-style: preserve-3d;
        transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid black;
        border-radius: 40px;
    }

    .face {
        position: absolute;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        width: 100%;
        height: 100%;
        padding: 24px;
        color: white;
        font-size: 3rem;
        border-radius: 40px;
        backface-visibility: hidden;
    }

    .front {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        background-color: pink;
    }

    .back {
        display: flex;
        flex-direction: column;
        justify-content: space-around;
        align-items: center;
        background-color: blue;
        transform: rotateY(180deg);
    }

    .button {
        position: relative;
        display: flex;
        justify-content: center;
        align-items: center;
        top: 20px;
        width: 400px;
        height: 100px;
        border: 1px solid black;
        border-radius: 40px;
    }

    button {
        cursor: pointer;
    }

    .button>button {
        width: 150px;
        height: 50px;
        margin: 10px;
        font-size: 1.6rem;
        border: 1px solid black;
        border-radius: 40px;
    }

    button#audio {
        width: 40px;
        height: 40px;
        margin: 10px;
        vertical-align: middle;
        font-size: 1rem;
        border: 1px solid black;
        border-radius: 10px;
    }

    .word {
        position: relative;
        bottom: 40px;
        font-size: 4rem;
    }

    .part-of-speech {
        margin: 20px;
        font-size: 1.6rem;
    }

    .phonetic {
        font-size: 1.6rem;
        font-family: "Lucida Sans Unicode", "Arial Unicode MS", "Segoe UI", sans-serif;
    }

    .definition {
        height: 40%;
        text-align: justify;
        font-size: 1.4rem;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .translation {
        display: block;
        text-align: justify;
        font-size: 1.4rem;
    }
</style>

<!-- <a href="./batch_fetch.php" style="position: fixed; right: 0; bottom: 10px; width: 240px; height: 120px; font-size: 3rem;">prefetch</a> -->

<div class="container">
    <div class="card-board" id="card-board" onclick="flipCard(event)">
        <div class="card" id="card">
            <div class="face front">
                <p class="word" id="word">word</p>
                <div style="position: absolute; bottom: 80px; display: flex; justify-content: center; align-items :center;">
                    <p class="part-of-speech" id="part-of-speech">part of speech</p>
                    <p class="phonetic" id="phonetic">phonetic</p>
                    <button class="audio" id="audio" onclick="playAudio(event)">發音</button>
                </div>
            </div>
            <div class="face back">
                <p class="translation" id="translation">中文翻譯</p>
                <p class="definition" id="definition">english definition</p>
            </div>
        </div>
    </div>

    <div class="button">
        <button onclick="shuffleCard()">洗牌</button>
        <button id="drawCard" onclick="drawCard()">抽牌</button>
    </div>
</div>

<script>
    const card = document.getElementById('card');

    let degree = 0;
    let currentAudioUrl = "";

    function flipCard(event) {
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

    card.addEventListener('transitionend', () => {
        card.style.transition = "none";

        degree %= 360;
        card.style.transform = `rotateY(${degree}deg)`;
    });

    function drawCard() {
        card.style.transition = "transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)";

        degree = 0;
        card.style.transform = `rotateY(${degree}deg)`;

        fetch('api.php?action=draw').then(response => response.json()).then(data => {
            if (data.status === 'success') {
                document.getElementById('word').innerText = data.word;
                document.getElementById('part-of-speech').innerText = data.part_of_speech;
                document.getElementById('phonetic').innerText = data.phonetic;
                document.getElementById('definition').innerText = data.definition;
                document.getElementById('translation').innerText = data.translation;

                currentAudioUrl = data.audio;
            } else {
                document.getElementById('word').innerText = "draw fail.";
            }
        }).catch(error => {
            console.error("error", error);
            document.getElementById('word').innerText = "wrong connection";
        });
    };

    function shuffleCard() {
        card.style.transition = "transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)";

        degree = 0;
        card.style.transform = `rotateY(${degree}deg)`;

        fetch('api.php?action=shuffle').then(response => response.json()).then(data => {
            if (data.status === 'success') {
                document.getElementById('word').innerText = data.word;
                document.getElementById('part-of-speech').innerText = data.part_of_speech;
                document.getElementById('phonetic').innerText = data.phonetic;
                document.getElementById('definition').innerText = data.definition;
                document.getElementById('translation').innerText = data.translation;

                currentAudioUrl = data.audio;
            } else {
                response.text().then(rawText => {
    document.getElementById('word').innerText = rawText;
});
            }
        }).catch(error => {
            console.error("error", error);
            document.getElementById('word').innerText = "wrong connection";
        });
    };

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

    // const randomSleep = () => {
    //     let ms = 1000 + 1000 * Math.random();
    //     return new Promise(resolve => setTimeout(resolve, ms));
    // };

    // async function myFetch(){
    //     let i;
    //     let drawCardvar = document.getElementById('drawCard');
    //     for(i = 0; i < 10000 ; i++){
    //         drawCardvar.click();
    //         await randomSleep();
    //     }
    // };
</script>