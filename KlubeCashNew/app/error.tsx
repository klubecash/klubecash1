"use client";

export default function HomeError({ reset }: { reset: () => void }) {
  return (
    <main className="main-content">
      <section className="section">
        <div className="container text-center">
          <h1>Não foi possível carregar esta página.</h1>
          <p>Tente novamente em alguns instantes.</p>
          <button type="button" className="btn btn-primary" onClick={reset}>Tentar novamente</button>
        </div>
      </section>
    </main>
  );
}
