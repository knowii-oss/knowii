describe('client-ui: Subscription component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=subscription--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to Subscription!');
  });
});
