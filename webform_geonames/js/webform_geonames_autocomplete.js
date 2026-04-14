document.addEventListener("DOMContentLoaded", function () {
  const countryField = document.querySelector("#edit-country-select");
  let debounceTimer;

  // Attach autocomplete functionality to city input fields
  function attachAutocomplete(cityField) {
    if (!cityField) return;
    
    cityField.addEventListener("input", debounce(fetchCities, 500)); // Fetch cities with debounce
    cityField.addEventListener("focus", handleCityFocus); // Show suggestions on focus
  }

  // Initialize city input fields with the specific CSS class
  function initializeCityFields() {
    const cityFields = document.querySelectorAll(".webform-city-autocomplete"); // Only target inputs with this class
    cityFields.forEach(attachAutocomplete);
  }

  // Fetch city suggestions from API
  async function fetchCities(event) {
    const cityField = event.target;
    const city = cityField.value.trim();
    const countryCode = await getCountryCode();
    if (!countryCode || !city) {
      hideSuggestions(cityField);
      return;
    }

    const url = `/webform-geonames/autocomplete?query=${encodeURIComponent(city)}&country_code=${countryCode}`;
    try {
      const response = await fetch(url);
      if (!response.ok) throw new Error("Failed to fetch");
      const data = await response.json();
      renderSuggestions(cityField, data);
    } catch (error) {
      console.error("Error fetching cities:", error);
    }
  }

  // Handle country selection change
  async function handleCountryChange(event) {
    const selectedCountry = event.target.value;
    const countryCode = await fetchCountryCode(selectedCountry);

    document.querySelectorAll(".webform-city-autocomplete").forEach(input => {
      input.setAttribute("data-country-code", countryCode);
      input.value = ""; // Clear city field
      hideSuggestions(input);
    });
  }

  // Fetch country code from API
  async function fetchCountryCode(countryName) {
    const formattedCountryName = countryName.replace(/ /g, "%20");
    const url = `https://restcountries.com/v3.1/name/${formattedCountryName}`;
    try {
      const response = await fetch(url);
      if (!response.ok) throw new Error("Failed to fetch country code");
      const data = await response.json();
      return data[0]?.cca2 || null;
    } catch (error) {
      console.error("Error fetching country code:", error);
      return null;
    }
  }

  // Get country code based on selected country
  async function getCountryCode() {
    return countryField ? await fetchCountryCode(countryField.value) : null;
  }

  // Render city suggestions dropdown
  function renderSuggestions(cityField, cities) {
    let suggestionContainer = document.querySelector(`#city-suggestions-${cityField.dataset.id}`);
    if (!suggestionContainer) {
      suggestionContainer = document.createElement("div");
      suggestionContainer.id = `city-suggestions-${cityField.dataset.id}`;
      suggestionContainer.classList.add("city-suggestions");
      suggestionContainer.style.position = "absolute";
      suggestionContainer.style.zIndex = "999";
      suggestionContainer.style.backgroundColor = "white";
      suggestionContainer.style.border = "1px solid #ccc";
      document.body.appendChild(suggestionContainer);
    }

    suggestionContainer.innerHTML = "";

    // Format city suggestions
    const filteredCities = cities
      .filter(city => !/\d/.test(city.label))
      .map(city => {
        const [cityName, ...rest] = city.label.split(",");
        return { label: cityName.trim(), details: rest.join(",").trim(), value: city.value };
      })
      .slice(0, 10);

    // Populate suggestions dropdown
    if (filteredCities.length > 0) {
      filteredCities.forEach(city => {
        const suggestionItem = document.createElement("div");
        suggestionItem.innerHTML = `<strong>${city.label}</strong><br><small>${city.details}</small>`;
        suggestionItem.style.padding = "8px";
        suggestionItem.style.cursor = "pointer";
        suggestionItem.style.transition = "background-color 0.2s ease";
        suggestionItem.addEventListener("mouseenter", () => (suggestionItem.style.backgroundColor = "#f5f5f5"));
        suggestionItem.addEventListener("mouseleave", () => (suggestionItem.style.backgroundColor = "white"));

        suggestionItem.addEventListener("click", () => {
          cityField.value = city.label;
          hideSuggestions(cityField);
        });

        suggestionContainer.appendChild(suggestionItem);
      });
    } else {
      const noResults = document.createElement("div");
      noResults.textContent = "No cities found.";
      suggestionContainer.appendChild(noResults);
    }

    // Position suggestions container
    const rect = cityField.getBoundingClientRect();
    suggestionContainer.style.left = `${rect.left}px`;
    suggestionContainer.style.top = `${rect.bottom + window.scrollY}px`;
    suggestionContainer.style.width = `${rect.width}px`;
  }

  // Hide suggestions dropdown
  function hideSuggestions(cityField) {
    const suggestionContainer = document.querySelector(`#city-suggestions-${cityField.dataset.id}`);
    if (suggestionContainer) {
      suggestionContainer.innerHTML = "";
    }
  }

  // Close suggestions when clicking outside
  document.addEventListener("click", function (event) {
    document.querySelectorAll(".city-suggestions").forEach(container => {
      if (!container.contains(event.target) && !document.querySelector(`[data-id="${container.id.replace("city-suggestions-", "")}"]`)?.contains(event.target)) {
        container.innerHTML = "";
      }
    });
  });

  // Handle focus on city field
  function handleCityFocus(event) {
    const cityField = event.target;
    const city = cityField.value;
    getCountryCode().then(countryCode => {
      if (city && countryCode) {
        fetchCities({ target: cityField });
      }
    });
  }

  // Debounce function to limit API calls
  function debounce(func, delay) {
    return function (...args) {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => func(...args), delay);
    };
  }

  // Assign unique IDs to city fields
  function assignCityFieldIds() {
    document.querySelectorAll(".webform-city-autocomplete").forEach((input, index) => {
      input.dataset.id = index;
    });
  }

  // Observe dynamically added city fields
  const observer = new MutationObserver(mutations => {
    mutations.forEach(mutation => {
      mutation.addedNodes.forEach(node => {
        if (node.nodeType === 1) {
          const newCityFields = node.querySelectorAll(".webform-city-autocomplete");
          newCityFields.forEach(attachAutocomplete);
        }
      });
    });
  });
  observer.observe(document.body, { childList: true, subtree: true });

  // Initialize fields on page load
  assignCityFieldIds();
  initializeCityFields();
  if (countryField) {
    countryField.addEventListener("change", handleCountryChange);
  }
});
