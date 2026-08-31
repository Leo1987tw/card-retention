<!-- <a href="./batch_fetch.php" style="position: fixed; right: 0; bottom: 10px; width: 240px; height: 120px; font-size: 3rem;">prefetch</a> -->

<div class="container">
    <div class="card-board" id="card-board" onclick="flipCard(event)">
        <div class="card" id="card">
            <div class="face front">
                <div class="new">NEW</div>
                <div class="learning-statement">
                    <span class="learning"></span>
                    <span class="preview-count"></span>
                </div>
                <p class="word" id="word">word</p>
                <div style="position: absolute; bottom: 80px; display: flex; justify-content: center; align-items :center;">
                    <p class="category1" id="part-of-speech">part of speech</p>
                    <p class="category2" id="phonetic">phonetic</p>
                    <button class="audio" id="audio" onclick="pronounce(event)">發音</button>
                </div>
            </div>
            <div class="face back">
                <p class="translation" id="translation">中文翻譯</p>
                <p class="definition" id="definition">english definition</p>
            </div>
        </div>
    </div>

    <div class="true-false">
        <button onclick="drawCard(correct, event)">答對了</button>
        <button onclick="drawCard(wrong, event)">答錯了</button>
    </div>
</div>