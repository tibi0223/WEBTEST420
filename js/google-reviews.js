// Google Reviews Live API Integration
const GOOGLE_API_KEY = 'AIzaSyA6O4pDsA_TSH_ZR0tTDw0N1I5WCuzd5ao';
const PLACE_ID = 'ChIJY7R4sP3XQUcRzGfW2k7tC4k'; // Nexus Klíma Google Places ID (frissíthető)

// Google Reviews API
async function fetchGoogleReviews() {
    try {
        const response = await fetch(`https://maps.googleapis.com/maps/api/place/details/json?place_id=${PLACE_ID}&fields=rating,reviews&key=${GOOGLE_API_KEY}`);
        const data = await response.json();
        
        if (data.status === 'OK' && data.result) {
            return {
                rating: data.result.rating,
                totalReviews: data.result.user_ratings_total,
                reviews: data.result.reviews || []
            };
        }
        throw new Error('API response not OK');
    } catch (error) {
        console.error('Error fetching Google reviews:', error);
        return null;
    }
}

// Csillagok generálása
function generateStars(rating) {
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;
    let stars = '';
    
    for (let i = 0; i < fullStars; i++) {
        stars += '★';
    }
    if (hasHalfStar && fullStars < 5) {
        stars += '☆';
    }
    for (let i = stars.length; i < 5; i++) {
        stars += '☆';
    }
    
    return stars;
}

// Értékelések frissítése a weboldalon
async function updateGoogleReviews() {
    const reviewsData = await fetchGoogleReviews();
    
    if (reviewsData) {
        // Frissítjük a fő értékelés megjelenítést
        const ratingElement = document.querySelector('.reviews__rating');
        const starsElement = document.querySelector('.reviews__stars');
        
        if (ratingElement) {
            ratingElement.textContent = reviewsData.rating.toFixed(1);
        }
        
        if (starsElement) {
            starsElement.textContent = generateStars(reviewsData.rating);
        }
        
        // Frissítjük a vélemény kártyákat is, ha vannak újabbak
        updateReviewCards(reviewsData.reviews);
        
        console.log('Google reviews updated successfully:', reviewsData);
    } else {
        // Fallback: ha nem sikerül lekérni, megtartjuk az alapértelmezett értékeket
        console.log('Using fallback values for Google reviews');
    }
}

// Vélemény kártyák frissítése
function updateReviewCards(reviews) {
    const carousel = document.getElementById('reviewsCarousel');
    if (!carousel || !reviews.length) return;
    
    // Legfeljebb 3 legfrissebb vélemény megjelenítése
    const latestReviews = reviews.slice(0, 3);
    
    carousel.innerHTML = latestReviews.map(review => `
        <div class="reviews__card">
            <div class="reviews__card-top">
                <div class="reviews__avatar">${review.author_name.charAt(0).toUpperCase()}</div>
                <div class="reviews__info">
                    <span class="reviews__name">${review.author_name}</span>
                    <span class="reviews__stars-row">${generateStars(review.rating)}</span>
                </div>
            </div>
            <p class="reviews__text">"${review.text}"</p>
        </div>
    `).join('');
}

// Automatikus frissítés oldalbetöltéskor és óránként
document.addEventListener('DOMContentLoaded', function() {
    updateGoogleReviews();
    
    // Óránként frissítünk (3600000 ms)
    setInterval(updateGoogleReviews, 3600000);
});

// Manuális frissítés lehetőség
window.updateGoogleReviewsManually = updateGoogleReviews;
