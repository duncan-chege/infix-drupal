document.addEventListener("DOMContentLoaded", function () {
  let splide_hero = new Splide('.splide.hero-images', {
    type: 'loop',
    perPage: 1,
    autoplay: true,
    speed: 300,
  });
  splide_hero.mount();
});
