import PaymentSandboxController from '@/actions/App/Http/Controllers/PaymentSandboxController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Form, Head } from '@inertiajs/react';

type Props = {
    payment: {
        reference: string;
        amount_display: string;
        currency: string;
        status: string;
    };
    event: { title: string; slug: string };
    organizer: { slug: string; name: string };
};

export default function PaymentSandbox({ payment, event, organizer }: Props) {
    return (
        <>
            <Head title={`Pay · ${event.title}`} />
            <div className="mx-auto flex w-full max-w-xl flex-col gap-6 p-6">
                <header className="space-y-2">
                    <Badge variant="outline">Sandbox checkout</Badge>
                    <h1 className="text-2xl font-semibold">{event.title}</h1>
                    <p className="text-muted-foreground">
                        Hosted by {organizer.name}
                    </p>
                </header>

                <section className="rounded-xl border p-6">
                    <dl className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt className="text-xs text-muted-foreground uppercase">
                                Reference
                            </dt>
                            <dd className="mt-1 font-mono">
                                {payment.reference}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground uppercase">
                                Amount
                            </dt>
                            <dd className="mt-1 text-lg font-semibold">
                                {payment.amount_display}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs text-muted-foreground uppercase">
                                Status
                            </dt>
                            <dd className="mt-1 capitalize">
                                {payment.status}
                            </dd>
                        </div>
                    </dl>
                </section>

                <p className="text-sm text-muted-foreground">
                    This is a sandbox checkout. In production the attendee would
                    be redirected to a real payment provider. Click a button to
                    simulate the outcome.
                </p>

                <div className="flex gap-3">
                    <Form
                        {...PaymentSandboxController.complete.form({
                            reference: payment.reference,
                        })}
                    >
                        {({ processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="outcome"
                                    value="succeed"
                                />
                                <Button type="submit" disabled={processing}>
                                    Simulate successful payment
                                </Button>
                            </>
                        )}
                    </Form>
                    <Form
                        {...PaymentSandboxController.complete.form({
                            reference: payment.reference,
                        })}
                    >
                        {({ processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="outcome"
                                    value="fail"
                                />
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Simulate failed payment
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}
