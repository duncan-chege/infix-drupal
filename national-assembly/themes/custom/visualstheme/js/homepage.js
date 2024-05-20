document.addEventListener("DOMContentLoaded", function () {
  let more_articles = new Splide('.more-articles .splide', {
    arrows: false,
    perPage: 3,
    pagination: true,
    autoplay: true,
    speed: 250,
    breakpoints: {
      1024: {
        perPage: 2,
      },
      640: {
        perPage: 1,
      },
    }
  });
  more_articles.mount();
});
