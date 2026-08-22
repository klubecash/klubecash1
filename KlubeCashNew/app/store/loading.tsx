export default function StoreLoading() {
  return (
    <div className="store-stack">
      <div className="store-skeleton store-skeleton-title" />
      <div className="store-grid store-grid-4">
        {[1, 2, 3, 4].map((item) => (
          <div className="store-skeleton store-skeleton-card" key={item} />
        ))}
      </div>
      <div className="store-skeleton store-skeleton-panel" />
    </div>
  );
}
