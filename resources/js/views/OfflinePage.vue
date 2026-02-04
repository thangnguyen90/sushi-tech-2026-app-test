<template>
    <div class="offline-wrapper">
        <div class="offline-content">
            <!-- icon -->
            <div class="offline-icon">
                <span>!</span>
            </div>

            <p class="offline-title">
                ページを開けませんでした
            </p>

            <p class="offline-subtitle">
                インターネット接続がありません
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">

</script>

<style scoped>
.offline-wrapper {
    min-height: 100vh;
    background: #000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.offline-content {
    text-align: center;
    padding: 24px;
}

.offline-icon {
    width: 72px;
    height: 72px;
    margin: 0 auto 24px;
    border-radius: 50%;
    border: 2px solid #cfe6ea;
    color: #9ccbd3;
    font-size: 36px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
}

.offline-title {
    font-size: 18px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 8px;
}

.offline-subtitle {
    font-size: 14px;
    color: #666;
}
</style>
