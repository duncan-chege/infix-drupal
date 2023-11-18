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


function startFadingContent(selector, totalDivs, interval) {
  var divs = document.querySelectorAll(selector);
  var currentIndex = 0;

  function displayDivs() {
    currentIndex = currentIndex % totalDivs;

    divs.forEach(function (div, i) {
      div.style.opacity = i === currentIndex ? 1 : 0;
    });

    setTimeout(function () {
      currentIndex++;
      displayDivs();
    }, interval);
  }

  // Start the display process
  displayDivs();
}

// Example usage
startFadingContent('.first-news-block > div', document.querySelectorAll('.first-news-block > div').length, 3000);
startFadingContent('.second-news-block > div', document.querySelectorAll('.second-news-block > div').length, 3000);
startFadingContent('.scroll-text > div', document.querySelectorAll('.scroll-text > div').length, 3000);
