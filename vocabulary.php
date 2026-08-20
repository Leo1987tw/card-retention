<!-- <a href="./batch_fetch.php" style="position: fixed; right: 0; bottom: 10px; width: 240px; height: 120px; font-size: 3rem;">prefetch</a> -->

<div class="container">
    <div class="card-board" id="card-board" onclick="flipCard(event)">
        <div class="card" id="card">
            <div class="face front">
                <div id="card-checkbox-container" class="card-checkbox-container" onclick="clickCheckbox(event);">
                    <span class="checkmark"></span>
                </div>
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

<div>
    <select name="vocabulary-category" id="vocabulary-category" onchange="changeVocabularySet(this.value)">
        <option value="vocabulary">english vocabulary</option>
        <option value="html">html</option>
        <option value="css">css</option>
    </select>
</div>