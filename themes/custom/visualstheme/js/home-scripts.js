document.addEventListener("DOMContentLoaded", function () {
    var splide = new Splide('.splide', {
        type    : 'loop',
        perPage : 1,
        autoplay: true,
        speed: 300,
    });
    splide.mount();
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


var divs = document.querySelectorAll('.first-news-block > div');
var totalDivs = divs.length;
var interval = 2000; // 3 seconds

function displayDivs(index) {
  var currentIndex = index % totalDivs;

  divs.forEach(function(div, i) {
    if (i === currentIndex) {
      div.style.opacity = 1;
    } else {
      div.style.opacity = 0;
    }
  });

  setTimeout(function() {
    displayDivs(currentIndex + 1);
  }, interval);
}

// Start the display process
displayDivs(0);

var divs2 = document.querySelectorAll('.second-news-block > div');
var totalDivs2 = divs2.length;
var interval2 = 3000; // 3 seconds

function displayDivs2(index2) {
  var currentIndex2 = index2 % totalDivs2;

  divs2.forEach(function(div, i) {
    if (i === currentIndex2) {
      div.style.opacity = 1;
    } else {
      div.style.opacity = 0;
    }
  });

  setTimeout(function() {
    displayDivs2(currentIndex2 + 1);
  }, interval2);
}

// Start the display process
displayDivs2(0);

var divs3 = document.querySelectorAll('.scroll-text > div');
var totalDivs3 = divs3.length;
var interval3 = 3000; // 3 seconds

function displayDivs3(index3) {
  var currentIndex3 = index3 % totalDivs3;

  divs3.forEach(function(div, i) {
    if (i === currentIndex3) {
      div.style.opacity = 1;
    } else {
      div.style.opacity = 0;
    }
  });

  setTimeout(function() {
    displayDivs3(currentIndex3 + 1);
  }, interval3);
}

// Start the display process
displayDivs3(0);