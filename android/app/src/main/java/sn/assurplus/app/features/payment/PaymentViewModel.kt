package sn.assurplus.app.features.payment

import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.currentCoroutineContext
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import sn.assurplus.app.app.AppEnvironment
import sn.assurplus.app.app.PaymentLaunchResult
import sn.assurplus.app.core.format.PhoneNumber
import sn.assurplus.app.core.model.Payment
import sn.assurplus.app.core.model.PaymentCreateRequest
import sn.assurplus.app.core.model.PaymentMethod
import sn.assurplus.app.core.network.APIError

/**
 * Pay → open the provider (app or hosted page) → come back → poll `GET /payments/{id}` until the server, informed
 * by the provider webhook, reports a final status. The client-side outcome is never trusted.
 */
class PaymentViewModel(env: AppEnvironment, val policyId: String, val amount: Long, val purpose: String) {
    enum class Phase { choosing, launching, confirming, finished }

    private val api = env.api
    private val launcher = env.paymentLauncher

    var methods by mutableStateOf<List<PaymentMethod>>(emptyList())
        private set
    var selectedMethod by mutableStateOf<String?>(null)
    var phone by mutableStateOf(PhoneNumber.formatInput(PhoneNumber.nationalDigits(env.session.user?.phone ?: "") ?: ""))
    var phase by mutableStateOf(Phase.choosing)
        private set
    var payment by mutableStateOf<Payment?>(null)
        private set
    var isLoadingMethods by mutableStateOf(false)
        private set
    var error by mutableStateOf<APIError?>(null)

    var pollIntervalMillis = 2_000L
    var pollTimeoutMillis = 180_000L

    val method: PaymentMethod? get() = methods.firstOrNull { it.code == selectedMethod }

    val canPay: Boolean
        get() {
            val method = method ?: return false
            if (!method.enabled || phase != Phase.choosing) return false
            return !method.requiresPhone || PhoneNumber.isValid(phone)
        }

    val succeeded: Boolean get() = payment?.isSuccessful == true

    suspend fun loadMethods() {
        isLoadingMethods = true
        try {
            methods = api.paymentMethods()
            if (selectedMethod == null) selectedMethod = methods.firstOrNull { it.enabled }?.code
            error = null
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            error = APIError.wrap(e)
        } finally {
            isLoadingMethods = false
        }
    }

    suspend fun pay() {
        val method = method ?: return
        if (!canPay) return
        error = null
        phase = Phase.launching
        try {
            val created = api.createPayment(
                PaymentCreateRequest(
                    policyId = policyId, method = method.code,
                    phone = if (method.requiresPhone) PhoneNumber.e164(phone) else null,
                    purpose = purpose, returnURL = RETURN_URL,
                )
            )
            payment = created
            val result = launcher.launch(created)
            if (result is PaymentLaunchResult.Failed) {
                error = APIError.Server(status = 0, code = "launch_failed", serverMessage = result.message, fields = emptyMap())
            }
            // Even when cancelled the user may have paid in the provider app: always ask the server.
            phase = Phase.confirming
            pollUntilFinal()
        } catch (e: CancellationException) {
            throw e
        } catch (e: Throwable) {
            error = APIError.wrap(e)
            phase = Phase.choosing
        }
    }

    suspend fun pollUntilFinal() {
        val id = payment?.id ?: return
        val deadline = System.currentTimeMillis() + pollTimeoutMillis
        while (System.currentTimeMillis() < deadline && currentCoroutineContext().isActive) {
            val latest = try {
                api.payment(id)
            } catch (e: CancellationException) {
                throw e
            } catch (e: Throwable) {
                null
            }
            if (latest != null) {
                payment = latest
                if (latest.isFinal) {
                    phase = Phase.finished
                    return
                }
            }
            delay(pollIntervalMillis)
        }
        // Still pending: the user can come back later; history shows the final status.
        phase = Phase.finished
    }

    /** After a failure, let the user pick another method. */
    fun retry() {
        payment = null
        phase = Phase.choosing
        error = null
    }

    companion object {
        const val RETURN_URL = "assurplus://payments/return"
    }
}
