document.addEventListener("DOMContentLoaded", function () {
  let splide_hero = new Splide('.splide.hero-images', {
    type: 'loop',
    perPage: 1,
    autoplay: true,
    speed: 300,
  });
  splide_hero.mount();

  let splide_committee = new Splide('.splide.committee-articles', {
    type   : 'loop',
    pagination: false,
    drag   : 'free',
    focus  : 'center',
    perPage: 3,
    autoScroll: {
      speed: 1,
    },
  });
  splide_committee.mount(window.splide.Extensions);

  let second_splide_committee = new Splide('.splide.second-committee-articles', {
    type   : 'loop',
    pagination: false,
    drag   : 'free',
    focus  : 'center',
    perPage: 3,
    autoScroll: {
      speed: 1,
    },
  });
  second_splide_committee.mount(window.splide.Extensions);
});

function handleMouseMove(event) {
  var countryTitle = event.target.getAttribute("title");
  var tooltip = document.getElementById("tooltip");

  var x = event.clientX;
  var y = event.clientY;

  var svgElement = event.target.ownerSVGElement; // Get the parent SVG element
  var svgRect = svgElement.getBoundingClientRect();

  // Check if the mouse event is within the boundaries of the SVG element
  if (
    x >= svgRect.left &&
    x <= svgRect.right &&
    y >= svgRect.top &&
    y <= svgRect.bottom
  ) {
    tooltip.style.left = x + 10 + "px";
    tooltip.style.top = y - 20 + "px";
    tooltip.innerHTML = countryTitle;
    tooltip.classList.add("active");
  } else {
    // If the mouse is outside the SVG element, hide the tooltip
    tooltip.classList.remove("active");
  }
}

// Hide the tooltip when the mouse leaves the SVG element
var svgElement = document.querySelector("svg");
svgElement.addEventListener("mouseleave", function () {
  var tooltip = document.getElementById("tooltip");
  tooltip.classList.remove("active");
});


let na_index = 0;
let senate_index = 0;
let notice_index = 0;

const na_items = document.querySelectorAll('.na-slider li');
const senate_items = document.querySelectorAll('.senate-slider li');
const notice_items = document.querySelectorAll('.notice-slider li');

setInterval(() => {
  // Hide the current list item
  na_items[na_index].classList.remove('ease-in');

  // Move to the next list item, or loop back to the start
  na_index = (na_index + 1) % na_items.length;

  // Show the next list item
  na_items[na_index].classList.add('ease-in');

  senate_items[senate_index].classList.remove('ease-in');

  senate_index = (senate_index + 1) % senate_items.length;

  senate_items[senate_index].classList.add('ease-in');
  
  notice_items[notice_index].classList.remove('ease-in');

  notice_index = (notice_index + 1) % notice_items.length;

  notice_items[notice_index].classList.add('ease-in');
  
}, 3000);
