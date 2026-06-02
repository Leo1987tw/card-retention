<style>
    .container {
        width: 100%;
        max-width: 400px;
        padding: 20px;
        text-align: center;
    }

    .card-stage {
        perspective: 1000px;
        width: 100%;
        height: 350px;
        margin: 30px;
        cursor: pointer;
    }

    .card {
        position: relative;
        width: 100%;
        height: 100%;
        box-shadow: 0px 10px 25px -5px rgba(0, 0, 0, 0.1), 0px 8px 10px -6px rgba(0, 0, 0, 0.1);
        border-radius: 20px;
        transform-style: preserve-3d;
        transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .face {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        position: absolute;
        width: 100%;
        height: 100%;
        padding: 24px;
        backface-visibility: hidden;
    }

    .front {}

    .back {}
</style>

<div class="container">
    <div class="card-stage" id="card" onclick="flipCard()">
        <div class="card">
            <div class="face front"></div>
            <div class="face back"></div>
        </div>
    </div>

    <button onclick="changeCard()"></button>
</div>

<script>
    const card = document.getElementById(card);

    function flipCard(){
        card.classList.toggle('is-flipped');
    }
    
    function changeCard(){
        card.classList.remove('is-flipped');
        console.log();
    }
</script>