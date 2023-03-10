describe('client-ui: Logo component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=logo--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to Logo!');
  });
});
