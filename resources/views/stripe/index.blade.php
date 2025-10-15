<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Stripe Payment</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://js.stripe.com/v3/"></script>
  <style>
    #card-element {
      border: 1px solid #ced4da;
      border-radius: 0.375rem;
      padding: 0.5rem;
    }

    .StripeElement--focus {
      border-color: #86b7fe;
      box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    .StripeElement--invalid {
      border-color: #dc3545;
    }
  </style>
</head>
<body class="bg-light d-flex align-items-center" style="height: 100vh;">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card shadow-sm">
          <div class="card-body p-4">
            <h4 class="mb-4">Stripe Payment</h4>
            <form id="payment-form">
              
              <div class="mb-3">
                <label for="card-element" class="form-label">Card Information</label>
                <div id="card-element"></div>
              </div>
              <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" placeholder="you@example.com" required>
              </div>
              <div class="mb-3">
                <label for="cardholder-name" class="form-label">Cardholder Name</label>
                <input type="text" class="form-control" id="cardholder-name" placeholder="John Doe" required>
              </div>
              <div class="mb-3">
                <label for="amount" class="form-label">Amount (USD)</label>
                <input type="number" class="form-control" id="amount" placeholder="Amount to pay (10)" required min="1">
              </div>
              <button type="submit" class="btn btn-primary w-100 mt-3">Pay</button>
            </form>
            <div id="card-errors" class="text-danger mt-2" role="alert"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="loader" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(255,255,255,0.8); z-index: 1050; justify-content: center; align-items: center;">
  <div class="spinner-border text-primary" role="status">
    <span class="visually-hidden">Loading...</span>
  </div>
</div>
  <!-- Include Notyf CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>

<script>
  const notyf = new Notyf({
    position: { x: 'right', y: 'top' },
    duration: 4000,
    types: [
      { type: 'success', background: 'green', dismissible: true },
      { type: 'error', background: 'red', dismissible: true }
    ]
  });

  const stripe = Stripe("{{ config('services.stripe.key') }}");
  const elements = stripe.elements();

  const card = elements.create("card", {
    hidePostalCode: true,
    style: {
      base: {
        fontSize: "16px",
        color: "#212529",
        fontFamily: "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial",
        "::placeholder": {
          color: "#6c757d"
        }
      },
      invalid: {
        color: "#dc3545"
      }
    }
  });

  card.mount("#card-element");

  const form = document.getElementById("payment-form");

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
  showLoader(); // 👈 Show loader

    const name = document.getElementById("cardholder-name").value;
    const email = document.getElementById("email").value;
    const amount = parseFloat(document.getElementById("amount").value);

    if (!amount || amount < 1) {
            hideLoader(); // 👈 Hide if invalid input

      notyf.error("Please enter a valid amount.");
      return;
    }

    const { token, error } = await stripe.createToken(card, { name });
    hideLoader();

    if (error) {
      notyf.error(error.message);
      return;
    }

    // Send token to server
    fetch("{{ route('stripe.post') }}", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": "{{ csrf_token() }}"
      },
      body: JSON.stringify({
        token: token.id,
        name: name,
        email: email,
        amount: amount,
      })
    })
    .then(res => res.json())
    .then(async data => {
      if (data.status === 'success') {
              hideLoader();

        notyf.success(data.message);
        setTimeout(() => {
    window.location.href = "{{ route('stripe.success') }}";
  }, 1500);
        form.reset();
        card.clear();
      } else if (data.status === 'fail') {
              hideLoader();

        notyf.error(Object.values(data.errors).join(', '));
      } else if (data.status === 'requires_action') {
        // Handle 3DS authentication
        const result = await stripe.handleCardAction(data.client_secret);
        if (result.error) {
                    hideLoader();

          notyf.error(result.error.message);
        } else {
          // Confirm the payment on the server
          fetch("{{ route('stripe.confirm') }}", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({
              payment_intent_id: result.paymentIntent.id
            })
          })
          .then(res => res.json())
          .then(confirmData => {
            if (confirmData.status === 'success') {
              notyf.success(confirmData.message);
              setTimeout(() => {
    window.location.href = "{{ route('stripe.success') }}";
  }, 1500);
              form.reset();
              card.clear();
            } else {
                          hideLoader();

              notyf.error(confirmData.message || 'Payment failed during confirmation.');
            }
          });
        }
      } else {
              hideLoader();

        notyf.error(data.message);
      }
    })
    .catch(() => {
            hideLoader();

      notyf.error("Something went wrong. Please try again.");
    });
  });
</script>

<!-- Confirm PaymentIntent on redirect -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  const payment_intent = urlParams.get('payment_intent');
  const payment_intent_client_secret = urlParams.get('payment_intent_client_secret');

  if (payment_intent && payment_intent_client_secret) {
    fetch("{{ route('stripe.confirm') }}", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': "{{ csrf_token() }}"
      },
      body: JSON.stringify({
        payment_intent_id: payment_intent,
        client_secret: payment_intent_client_secret
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        notyf.success(data.message || 'Payment confirmed successfully!');
       setTimeout(() => {
    window.location.href = "{{ route('stripe.success') }}";
  }, 1500);
      } else {
        notyf.error(data.message || 'Payment confirmation failed.');
      }
    })
    .catch(() => {
      notyf.error('Network or server error during payment confirmation.');
    });
  }
});
</script>
<script>
    function showLoader() {
  document.getElementById("loader").style.display = "flex";
}

function hideLoader() {
  document.getElementById("loader").style.display = "none";
}
</script>
</body>
</html>
