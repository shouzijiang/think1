<template>
  <view v-if="show" class="album-picker">
    <view class="ap-backdrop" @click="$emit('close')" />
    <view class="ap-wrapper">
      <view class="ap-card">
        <view class="ap-ribbon" />

        <view class="ap-title-bar">
          <text class="ap-title">请选择专辑</text>
        </view>

        <scroll-view scroll-y class="ap-scroll" :show-scrollbar="false">
          <view class="bookshelf">
            <!-- 经典专辑 -->
            <view class="book" hover-class="book--hover" @click="selectMid">
              <view class="book-cover">
                <image class="book-img" :src="imgUrl('classic')" mode="aspectFill" />
                <view class="book-mask" />
                <text class="book-label">经典专辑</text>
                <text class="book-count" style="background: #d4a020">经典</text>
              </view>
            </view>

            <!-- 分类专辑 -->
            <view
              v-for="cat in categories"
              :key="cat.slug"
              :class="['book', { 'book--locked': !isUnlocked(cat.slug) }]"
              hover-class="book--hover"
              @click="onCategoryClick(cat)"
            >
              <view class="book-cover">
                <image class="book-img" :src="imgUrl(cat.slug)" mode="aspectFill" />
                <view class="book-mask" />
                <view v-if="!isUnlocked(cat.slug)" class="book-lock-overlay">
                  <text class="book-lock-icon">🔒</text>
                  <text class="book-lock-hint">看广告解锁</text>
                </view>
                <text class="book-label">{{ cat.label }}</text>
                <text
                  v-if="catTotal(cat) > 0"
                  class="book-count"
                  :style="{ background: !isUnlocked(cat.slug) ? '#b0b0b0' : '#6C5CE7' }"
                  >{{ catTotal(cat) }}关</text
                >
              </view>
            </view>
          </view>

          <view class="ap-bottom" />
        </scroll-view>
      </view>

      <!-- 关闭按钮（紧贴卡片右上角外侧） -->
      <view class="ap-close" hover-class="ap-close--hover" @click="$emit('close')">
        <text class="ap-close-x">✕</text>
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { loadAlbumCategories } from "../constants/albumCategories";

const props = defineProps({
  show: { type: Boolean, default: false },
  unlockedAlbums: { type: Array, default: () => [] },
});

const emit = defineEmits(["close", "select", "unlock"]);

const categories = ref([]);
const unlockedSet = computed(() => new Set(props.unlockedAlbums || []));

// 弹窗打开时从服务端拉取最新专辑列表
watch(() => props.show, async (visible) => {
  if (visible) {
    categories.value = await loadAlbumCategories();
  }
});

function imgUrl(slug) {
  // icon 字段已经是完整 CDN URL，slug 兜底
  const cat = categories.value.find((c) => c.slug === slug);
  return (cat && cat.icon) || ('https://static2.sofun.online/categories/' + slug + '.jpg');
}

function catTotal(cat) {
  return cat.subTypes.reduce((s, sub) => s + sub.count, 0)
}

function isUnlocked(filterKey) {
  return unlockedSet.value.has(filterKey)
}

function selectMid() {
  emit("select", { type: "mid" })
}

function onCategoryClick(cat) {
  if (isUnlocked(cat.slug)) {
    emit("select", {
      type: "xhs_filtered",
      category: cat.slug,
      filterType: cat.subTypes.reduce((a, s) => { a.push(...s.answerTypes); return a }, []).join(","),
      label: cat.label,
    })
  } else {
    emit("unlock", { categorySlug: cat.slug, label: cat.label })
  }
}
</script>

<style lang="scss" scoped>
.album-picker {
  position: fixed;
  inset: 0;
  z-index: 998;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32rpx 24rpx;
  box-sizing: border-box;
}

.ap-backdrop {
  position: absolute;
  inset: 0;
  background: radial-gradient(
      ellipse 60% 40% at 50% 40%,
      rgba(180, 140, 100, 0.15),
      transparent 60%
    ),
    rgba(18, 12, 6, 0.65);
  backdrop-filter: blur(14px);
  animation: ap-fade-in 0.35s ease-out;
}

.ap-wrapper {
  position: relative;
  width: 100%;
  max-width: 660rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.ap-card {
  position: relative;
  width: 100%;
  max-width: 660rpx;
  max-height: 86vh;
  background: linear-gradient(175deg, #fdfaf4 0%, #f8f0e3 30%, #f2e6d2 100%);
  border-radius: 32rpx;
  border: 1rpx solid rgba(180, 150, 120, 0.25);
  display: flex;
  flex-direction: column;
  box-shadow: 0 32rpx 80rpx rgba(0, 0, 0, 0.45), 0 12rpx 28rpx rgba(0, 0, 0, 0.25),
    0 0 0 1rpx rgba(255, 255, 255, 0.5) inset, 0 2rpx 0 rgba(255, 255, 255, 0.3) inset;
  animation: ap-pop-in 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
  overflow: hidden;
}

.ap-ribbon {
  height: 4rpx;
  flex-shrink: 0;
  background: linear-gradient(
    90deg,
    transparent,
    #c8956c 20%,
    #d4a574 50%,
    #c8956c 80%,
    transparent
  );
}

.ap-title-bar {
  padding: 22rpx 28rpx 8rpx;
  flex-shrink: 0;
}

.ap-title {
  font-size: 32rpx;
  font-weight: 800;
  color: #5c3d2e;
  letter-spacing: 0.06em;
}

.ap-close {
  position: absolute;
  top: -20rpx;
  right: -8rpx;
  z-index: 10;
  width: 56rpx;
  height: 56rpx;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  .ap-close-x {
    font-size: 30rpx;
    color: #fff;
    line-height: 1;
  }
}
.ap-close--hover {
  background: rgba(0, 0, 0, 0.6);
  transform: scale(0.9);
}

.ap-scroll {
  flex: 1;
  padding: 18rpx 20rpx 8rpx;
  max-height: 76vh;
  box-sizing: border-box;
  width: 100%;
}
.ap-bottom {
  height: 28rpx;
}

.bookshelf {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14rpx;
  width: 100%;
  box-sizing: border-box;
}

/* ===== 书本 ===== */
.book {
  border-radius: 14rpx;
  overflow: hidden;
  box-shadow: 0 6rpx 18rpx rgba(0, 0, 0, 0.1), 2rpx 3rpx 0 rgba(0, 0, 0, 0.05);
  transition: transform 0.18s, box-shadow 0.18s, filter 0.18s;
  height: 210rpx;
}

.book--hover {
  transform: translateY(-5rpx) scale(1.04);
  box-shadow: 0 14rpx 28rpx rgba(0, 0, 0, 0.16), 2rpx 3rpx 0 rgba(0, 0, 0, 0.06);
}

.book--locked {
  opacity: 0.55;
  filter: grayscale(0.55);
}

/* 书封面 */
.book-cover {
  width: 100%;
  height: 100%;
  position: relative;
  overflow: hidden;
  background: #e8ddd0;

  .book-img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
  }

  .book-mask {
    position: absolute;
    inset: 0;
    background: linear-gradient(
      180deg,
      rgba(0, 0, 0, 0.05) 0%,
      rgba(0, 0, 0, 0) 40%,
      rgba(0, 0, 0, 0.45) 100%
    );
  }

  .book-label {
    position: absolute;
    bottom: 8rpx;
    left: 8rpx;
    font-size: 20rpx;
    font-weight: 700;
    color: #fff;
    letter-spacing: 0.04em;
    text-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.6);
    z-index: 1;
  }

  .book-count {
    position: absolute;
    bottom: 8rpx;
    right: 8rpx;
    font-size: 16rpx;
    font-weight: 700;
    color: #fff;
    padding: 1rpx 10rpx;
    border-radius: 999rpx;
    line-height: 1.4;
    z-index: 1;
  }

  .book-lock-overlay {
    position: absolute;
    inset: 0;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4rpx;
    background: rgba(0, 0, 0, 0.35);
  }

  .book-lock-icon {
    font-size: 36rpx;
    filter: drop-shadow(0 2rpx 4rpx rgba(0, 0, 0, 0.4));
  }

  .book-lock-hint {
    font-size: 22rpx;
    color: #fff;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-shadow: 0 1rpx 4rpx rgba(0, 0, 0, 0.5);
  }
}

/* ===== Animations ===== */
@keyframes ap-fade-in {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
@keyframes ap-pop-in {
  from {
    opacity: 0;
    transform: scale(0.88) translateY(40rpx);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}
</style>
