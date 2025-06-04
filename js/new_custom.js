$(document).ready(function () {
  // Membership form submission
  $(".membership-form").on("submit", function (e) {
    e.preventDefault();

    if (!validateForm($(this))) {
      return;
    }

    if (!$("#signup-agree").is(":checked")) {
      alert("Please accept the Terms & Conditions");
      return;
    }

    const phone = $('input[name="cf-phone"]').val();
    if (!validateIndianPhoneNumber(phone)) {
      alert(
        "Please enter a valid Indian mobile number (10 digits starting with 6, 7, 8, or 9)"
      );
      return;
    }

    const form = $(this);
    const submitButton = form.find("#submit-button");
    const originalButtonText = submitButton.text();
    submitButton.prop("disabled", true).text("Sending...");

    $.ajax({
      url: "php/admin/save_enquiry.php",
      method: "POST",
      data: form.serialize(),
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          alert("Thank you for your enquiry! We will contact you soon.");
          $("#membershipForm").modal("hide");
          form[0].reset();
        } else {
          alert(
            response.message || "Error submitting enquiry. Please try again."
          );
        }
      },
      error: function () {
        alert("Error submitting enquiry. Please try again.");
      },
      complete: function () {
        submitButton.prop("disabled", false).text(originalButtonText);
      },
    });
  });

  // Contact form submission
  $(".contact-form").on("submit", function (e) {
    e.preventDefault();

    if (!validateForm($(this))) {
      return;
    }

    const phone = $(this).find('input[name="cf-phone"]').val();
    if (phone && !validateIndianPhoneNumber(phone)) {
      alert(
        "Please enter a valid Indian mobile number (10 digits starting with 6, 7, 8, or 9)"
      );
      return;
    }

    const form = $(this);
    const submitButton = form.find("#submit-button");
    const originalButtonText = submitButton.text();
    submitButton.prop("disabled", true).text("Sending...");

    $.ajax({
      url: "php/admin/save_enquiry.php",
      method: "POST",
      data: form.serialize(),
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          alert("Thank you for your message! We will get back to you soon.");
          form[0].reset();
        } else {
          alert(response.message || "Error sending message. Please try again.");
        }
      },
      error: function () {
        alert("Error sending message. Please try again.");
      },
      complete: function () {
        submitButton.prop("disabled", false).text(originalButtonText);
      },
    });
  });

  // Phone number input handler
  $('input[name="cf-phone"]').on("input", function () {
    let value = $(this).val().replace(/\D/g, ""); // Remove non-digits

    // Limit to 10 digits
    if (value.length > 10) {
      value = value.slice(0, 10);
    }

    // Just keep the plain 10 digits without formatting
    $(this).val(value);
  });

  // Update phone input attributes
  $('input[name="cf-phone"]').each(function () {
    $(this).attr({
      maxlength: "10",
      minlength: "10",
      placeholder: "Enter 10 digit mobile number",
      pattern: "[6-9][0-9]{9}",
      title:
        "Please enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9",
    });
  });
});

// Validation functions
function validateIndianPhoneNumber(phone) {
  const cleanPhone = phone.replace(/\D/g, "");
  return /^[6-9]\d{9}$/.test(cleanPhone);
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function validateForm(form) {
  const email = form.find('input[name="cf-email"]').val();
  const name = form.find('input[name="cf-name"]').val();

  if (!name) {
    alert("Please enter your name");
    return false;
  }

  if (!isValidEmail(email)) {
    alert("Please enter a valid email address");
    return false;
  }

  return true;
}
